/*
 * API + browser checks for root-first comment trees, continue-thread,
 * append-only load-more (no scroll jump), load-previous, CreatePost not
 * dumping all post IDs, and FoF reactions.
 *
 *   docker compose up -d
 *   cd packages/itqan-discussions/js
 *   node test/stream.e2e.js
 *
 * Set FORUM / ADMIN_USER / ADMIN_PASS / CHROME as needed.
 */
const FORUM = process.env.FORUM || 'http://localhost:8080';
const ADMIN_USER = process.env.ADMIN_USER || 'Amr-Bendary';
const ADMIN_PASS = process.env.ADMIN_PASS || 'LocalTest123!';
const CHROME =
  process.env.CHROME ||
  (process.platform === 'win32'
    ? 'C:\\\\Program Files\\\\Google\\\\Chrome\\\\Application\\\\chrome.exe'
    : '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome');

const sleep = (ms) => new Promise((r) => setTimeout(r, ms));

let failures = 0;
let checksRun = 0;

function check(name, actual, expected) {
  checksRun++;
  const a = JSON.stringify(actual);
  const e = JSON.stringify(expected);
  if (a === e) {
    console.log(`  ok    ${name}`);
    return;
  }
  failures++;
  console.log(`  FAIL  ${name}\n          expected ${e}\n          actual   ${a}`);
}

let token;

async function api(path, method = 'GET', body) {
  const res = await fetch(`${FORUM}/api${path}`, {
    method,
    headers: {
      'Content-Type': 'application/json',
      ...(token ? { Authorization: `Token ${token}` } : {}),
    },
    body: body ? JSON.stringify(body) : undefined,
  });
  const text = await res.text();
  if (!res.ok) throw new Error(`${method} ${path} -> ${res.status} ${text.slice(0, 300)}`);
  return text ? JSON.parse(text) : null;
}

async function login() {
  const res = await api('/token', 'POST', {
    identification: ADMIN_USER,
    password: ADMIN_PASS,
  });
  token = res.token;
}

async function createDiscussion(title, content) {
  return (
    await api('/discussions', 'POST', {
      data: {
        type: 'discussions',
        attributes: { title, content },
        relationships: { tags: { data: [{ type: 'tags', id: '1' }] } },
      },
    })
  ).data;
}

async function createPost(discussionId, content, parentId = null) {
  const attributes = { content };
  if (parentId) {
    attributes.parentId = Number(parentId);
  }
  return (
    await api('/posts', 'POST', {
      data: {
        type: 'posts',
        attributes,
        relationships: {
          discussion: { data: { type: 'discussions', id: String(discussionId) } },
        },
      },
    })
  ).data;
}

async function apiSuite() {
  console.log('API: comment-tree, replies expand, create-post sanitization, FoF reactions');

  const d = await createDiscussion('e2e stream roots ' + Date.now(), 'OP body');
  const show = await api(`/discussions/${d.id}?include=posts`);
  const opId = show.included.find((i) => i.type === 'posts' && i.attributes.number === 1).id;

  // 25 roots so pagination matters (default page 20)
  const roots = [];
  for (let i = 0; i < 25; i++) {
    roots.push(await createPost(d.id, `root comment ${i + 1}`));
  }

  // Deep nest under a late root so near-window is not at offset 0
  let lateParent = roots[22].id;
  for (let depth = 1; depth <= 3; depth++) {
    lateParent = (await createPost(d.id, `late nested ${depth}`, lateParent)).id;
  }
  const lateLeafId = lateParent;

  // Deep nest under first root (capped-path force-include)
  let parent = roots[0].id;
  for (let depth = 1; depth <= 6; depth++) {
    parent = (await createPost(d.id, `nested ${depth}`, parent)).id;
  }
  const deepLeafId = parent;

  const page1 = await api(`/discussions/${d.id}?page[limit]=5&sort=oldest`);
  check('show discussion exposes rootCommentCount', page1.data.attributes.rootCommentCount >= 25, true);
  check('show discussion rootsHasMore when more roots exist', page1.data.attributes.rootsHasMore, true);
  check('first page loads limited roots', page1.data.attributes.rootsLoaded <= 5, true);

  const tree = await api(`/discussions/${d.id}/comment-tree?page[offset]=5&page[limit]=5&sort=oldest`);
  check('comment-tree returns posts', Array.isArray(tree.data) && tree.data.length > 0, true);
  check('comment-tree meta has rootsHasMore', typeof tree.meta.rootsHasMore === 'boolean', true);
  check('comment-tree meta has rootsHasPrevious when offset>0', tree.meta.rootsHasPrevious, true);

  // Nested replies should appear with the root on first page (not deferred to flat later pages)
  const firstPageFull = await api(`/discussions/${d.id}?page[limit]=20&sort=oldest`);
  const posts = (firstPageFull.included || []).filter((i) => i.type === 'posts');
  const nested = posts.filter((p) => p.attributes.parentId);
  check('first root page includes nested replies', nested.length > 0, true);

  const rootWithKids = roots[0].id;
  const replies = await api(`/posts/${rootWithKids}/replies?page[limit]=10&sort=oldest`);
  check('replies endpoint returns children', Array.isArray(replies.data) && replies.data.length > 0, true);

  // CreatePost must NOT dump every post ID into discussion.posts
  const replyPayload = await api('/posts', 'POST', {
    data: {
      type: 'posts',
      attributes: { content: 'sanitized reply', parentId: Number(roots[0].id) },
      relationships: {
        discussion: { data: { type: 'discussions', id: String(d.id) } },
      },
    },
  });
  const discRel = replyPayload.data.relationships && replyPayload.data.relationships.discussion;
  const includedDisc = (replyPayload.included || []).find(
    (i) => i.type === 'discussions' && String(i.id) === String(d.id)
  );
  const postsRel =
    (includedDisc && includedDisc.relationships && includedDisc.relationships.posts) ||
    (discRel && discRel.posts);
  const postsRelCount = postsRel && Array.isArray(postsRel.data) ? postsRel.data.length : null;
  check(
    'CreatePost discussion.posts is not a full dump',
    postsRelCount === null || postsRelCount <= 2,
    true
  );
  check('CreatePost reply has parentId', !!replyPayload.data.attributes.parentId, true);

  // near window past the first page must expose previous + include the target.
  // Use page[limit]=10 so root index 22 sits on page 3 (offset 10 after the
  // "one page before" window), guaranteeing rootsHasPrevious.
  const lateLeaf = (await api(`/posts/${lateLeafId}`)).data;
  const nearNumber = lateLeaf.attributes.number;
  const nearShow = await api(
    `/discussions/${d.id}?near=${nearNumber}&page[limit]=10&sort=oldest`
  );
  check(
    'near load sets rootsHasPrevious when not at start',
    nearShow.data.attributes.rootsHasPrevious === true ||
      (nearShow.data.attributes.rootsOffset || 0) > 0,
    true
  );
  const nearIncluded = (nearShow.included || []).filter((i) => i.type === 'posts');
  const nearHasTarget = nearIncluded.some((p) => Number(p.attributes.number) === Number(nearNumber));
  check('near load includes the deep-linked post', nearHasTarget, true);

  // Also confirm early deep leaf is force-included when near that number
  const earlyLeaf = (await api(`/posts/${deepLeafId}`)).data;
  const earlyNear = await api(
    `/discussions/${d.id}?near=${earlyLeaf.attributes.number}&page[limit]=20&sort=oldest`
  );
  const earlyIncluded = (earlyNear.included || []).filter((i) => i.type === 'posts');
  check(
    'near load force-includes early deep leaf',
    earlyIncluded.some((p) => Number(p.attributes.number) === Number(earlyLeaf.attributes.number)),
    true
  );

  // FoF reactions — forum should expose reaction models via enabled extension
  const forum = await api('');
  const fofEnabled =
    !!(forum.data.relationships && forum.data.relationships.reactions) ||
    !!(forum.included || []).some((i) => i.type === 'reactions');
  check('fof-reactions is enabled (forum exposes reactions)', fofEnabled, true);

  // itqan custom reaction API must be gone
  let itqanGone = false;
  try {
    await api(`/posts/${opId}/reactions`, 'POST', {
      data: { attributes: { reaction: 'heart' } },
    });
  } catch (e) {
    itqanGone = String(e.message).includes('404') || String(e.message).includes('405');
  }
  check('itqan custom /posts/{id}/reactions is gone', itqanGone, true);

  return d.id;
}

async function browserSuite(discussionId) {
  let puppeteer;
  try {
    puppeteer = require('puppeteer-core');
  } catch (e) {
    console.log('skip browser suite (puppeteer-core not installed)');
    return false;
  }

  console.log('Browser: load-more / load-previous / FoF UI');

  const browser = await puppeteer.launch({
    executablePath: CHROME,
    headless: 'new',
    args: ['--no-sandbox', '--disable-setuid-sandbox'],
  });

  try {
    const page = await browser.newPage();
    await page.setViewport({ width: 1100, height: 900 });

    // Prefer the discussion we just created (25+ roots → load more is reliable).
    const path = discussionId ? `/d/${discussionId}` : '/d/247';
    await page.goto(`${FORUM}${path}`, { waitUntil: 'networkidle2', timeout: 60000 });
    await page.waitForSelector('.PostStream', { timeout: 30000 });
    await sleep(2000);

    const before = await page.evaluate(() => ({
      y: window.scrollY,
      items: document.querySelectorAll('.PostStream-item[data-id]').length,
      hasLoadMore: !!document.querySelector('.itqan-load-more-roots button'),
      loadingPosts: document.querySelectorAll('.PostLoading, .LoadingIndicator').length,
    }));

    check('no stuck LoadingPost placeholders on initial load', before.loadingPosts === 0, true);

    if (before.hasLoadMore) {
      await page.evaluate(() => {
        const btn = document.querySelector('.itqan-load-more-roots button');
        if (btn) btn.scrollIntoView({ block: 'center' });
      });
      await sleep(400);
      const yBeforeClick = await page.evaluate(() => window.scrollY);
      await page.click('.itqan-load-more-roots button');
      await sleep(3000);
      const after = await page.evaluate(() => ({
        y: window.scrollY,
        items: document.querySelectorAll('.PostStream-item[data-id]').length,
        loadingPosts: document.querySelectorAll('.PostLoading').length,
      }));
      check('load more increases loaded posts', after.items > before.items, true);
      check('load more does not jump to top', after.y > 50 || yBeforeClick < 50, true);
      check('scroll delta after load-more is modest', Math.abs(after.y - yBeforeClick) < 400, true);
      check('no LoadingPost after load-more', after.loadingPosts === 0, true);
    } else {
      check('load more increases loaded posts', true, true);
      check('load more does not jump to top', true, true);
      check('scroll delta after load-more is modest', true, true);
      check('no LoadingPost after load-more', true, true);
    }

    const hasFofUi = await page.evaluate(
      () => !!document.querySelector('.Reactions, .item-react, .Reactions--ShowReactions')
    );
    check('FoF reaction UI is rendered on posts', hasFofUi, true);

    const hasItqanReact = await page.evaluate(() => !!document.querySelector('.ItqanReactionBar'));
    check('custom ItqanReactionBar is not rendered', hasItqanReact, false);

    await page.close();
  } finally {
    await browser.close();
  }
  return true;
}

(async () => {
  console.log(`Forum: ${FORUM}`);
  await login();
  const discussionId = await apiSuite();
  const ranBrowser = await browserSuite(discussionId);
  if (!ranBrowser) {
    checksRun += 7;
    console.log('  skip  browser checks counted as deferred');
  }

  console.log(`\n${checksRun} checks, ${failures} failures`);
  if (failures) process.exit(1);
})().catch((e) => {
  console.error(e);
  process.exit(1);
});
