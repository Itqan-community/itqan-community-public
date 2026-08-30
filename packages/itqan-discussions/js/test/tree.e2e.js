/*
 * End to end checks for the nested reply tree.
 *
 * The tree is drawn from data the browser holds rather than from anything the
 * server sends about it, and most of what can go wrong only goes wrong at
 * runtime: a parent that has been evicted from the post stream, a chain deeper
 * than the indent allows, two posts naming each other. None of that is
 * reachable from a unit test of the module, so this drives a real forum.
 *
 * It seeds its own discussions and asserts against the ids it gets back, so it
 * does not depend on the state of the database it runs against.
 *
 *   docker compose up -d
 *   cd packages/itqan-discussions/js
 *   npm install --no-save puppeteer-core
 *   node test/tree.e2e.js
 *
 * Chrome is used from wherever it is installed; set CHROME to override, and
 * FORUM / ADMIN_USER / ADMIN_PASS if the forum is not the default local one.
 */
const puppeteer = require('puppeteer-core');

const FORUM = process.env.FORUM || 'http://localhost:8080';
const ADMIN_USER = process.env.ADMIN_USER || 'admin';
const ADMIN_PASS = process.env.ADMIN_PASS || 'password123';
const CHROME =
  process.env.CHROME || '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome';

const sleep = (ms) => new Promise((r) => setTimeout(r, ms));

// Every case can bail early — a row that never rendered, an end of thread never
// reached — and an early return quietly removes the assertions below it. The
// count is asserted at the end so a short run is a failure rather than a pass
// with fewer checks in it than it should have had.
const EXPECTED_CHECKS = 16;

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

// ---------------------------------------------------------------- api ------

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
  if (!res.ok) throw new Error(`${method} ${path} -> ${res.status} ${text.slice(0, 200)}`);
  return text ? JSON.parse(text) : null;
}

const discussion = async (title, content) =>
  (
    await api('/discussions', 'POST', {
      data: {
        type: 'discussions',
        attributes: { title, content },
        relationships: { tags: { data: [{ type: 'tags', id: '1' }] } },
      },
    })
  ).data;

const post = async (discussionId, content) =>
  (
    await api('/posts', 'POST', {
      data: {
        type: 'posts',
        attributes: { content },
        relationships: {
          discussion: { data: { type: 'discussions', id: String(discussionId) } },
        },
      },
    })
  ).data;

const edit = (id, content) =>
  api(`/posts/${id}`, 'PATCH', {
    data: { type: 'posts', id: String(id), attributes: { content } },
  });

const firstPostOf = async (discussionId) =>
  (await api(`/discussions/${discussionId}?include=posts`)).included.find((i) => i.type === 'posts')
    .id;

// The format flarum/mentions writes when the reply button is pressed, and the
// one the tree reads back out of the rendered post to find a parent.
const mention = (postId) => `@"${ADMIN_USER}"#p${postId}`;

// ------------------------------------------------------------- browser -----

// Depth, hidden state and the toggle label for every post on screen, in the
// order the stream renders them.
const readStream = () =>
  Array.from(document.querySelectorAll('.PostStream-item'))
    .map((item) => {
      const article = item.querySelector('article');
      if (!article) return null;
      const classes = Array.from(article.classList);
      const depth = classes.find((c) => c.startsWith('itqanTree--depth'));
      return {
        number: item.getAttribute('data-number'),
        depth: depth ? Number(depth.slice(-1)) : 0,
        hidden: classes.includes('itqanTree--hidden'),
        hasToggle: !!article.querySelector('.itqanTree-toggle'),
        margin: getComputedStyle(item).marginInlineStart,
        padding: getComputedStyle(item).paddingInlineStart,
      };
    })
    .filter(Boolean);

async function visit(browser, path, { width = 1000, height = 1400 } = {}) {
  const page = await browser.newPage();
  await page.setViewport({ width, height });
  page.on('pageerror', (e) => {
    failures++;
    console.log('  FAIL  uncaught page error:', e.message);
  });
  await page.goto(`${FORUM}${path}`, { waitUntil: 'networkidle2' });
  await page.waitForSelector('.PostStream-item article', { timeout: 20000 });
  await sleep(2000);
  return page;
}

const visibleRows = () =>
  Array.from(document.querySelectorAll('.PostStream-item')).filter(
    (i) => getComputedStyle(i).display !== 'none'
  ).length;

const clickFirstToggle = () =>
  document.querySelector('.PostStream-item article .itqanTree-toggle').click();

// --------------------------------------------------------------- cases -----

async function shapeAndCollapse(browser) {
  console.log('shape, collapse, and the two things mentionedBy over-reports');

  const d = await discussion('e2e: shape', 'root');
  const root = await firstPostOf(d.id);
  const a = await post(d.id, `${mention(root)} first reply`);
  const b = await post(d.id, `${mention(a.id)} reply to the reply`);
  await post(d.id, `${mention(root)} second reply`);

  // Mentions this thread from somewhere else. It shows up in mentionedBy but is
  // not a reply here, and must not be counted or indented.
  const other = await discussion('e2e: elsewhere', 'unrelated');
  await post(other.id, `${mention(root)} linking across threads`);

  // Names the root in passing; its parent is `b`, so it belongs under `b`.
  await post(d.id, `${mention(b.id)} answering this, and also ${mention(root)} in passing`);

  const page = await visit(browser, `/d/${d.id}`);
  const rows = await page.evaluate(readStream);

  check('depths follow the reply chain', rows.map((r) => r.depth), [0, 1, 2, 1, 3]);
  check('a toggle appears only where a post has replies', rows.map((r) => r.hasToggle), [
    true, true, true, false, false,
  ]);

  const label = await page.$eval('.itqanTree-toggle', (el) => el.textContent.trim());
  check('the root counts its branch, not everyone who mentioned it', /\b4\b/.test(label), true);

  await page.evaluate(clickFirstToggle);
  await sleep(500);
  check('collapsing the root hides the whole branch', await page.evaluate(visibleRows), 2);

  await page.evaluate(clickFirstToggle);
  await sleep(500);
  check('expanding brings it all back', await page.evaluate(visibleRows), 6);

  await page.close();
}

async function depthCap(browser) {
  console.log('a chain deeper than the indent allows');

  const d = await discussion('e2e: depth', 'root');
  let previous = await firstPostOf(d.id);
  for (let level = 1; level <= 8; level++) {
    previous = (await post(d.id, `${mention(previous)} level ${level}`)).id;
  }

  const page = await visit(browser, `/d/${d.id}`, { width: 900, height: 2600 });
  const rows = await page.evaluate(readStream);
  check('the ladder stops at five', rows.map((r) => r.depth), [0, 1, 2, 3, 4, 5, 5, 5, 5]);
  await page.close();
}

async function mutualMention(browser) {
  console.log('two posts naming each other');

  const d = await discussion('e2e: cycle', 'root');
  const a = await firstPostOf(d.id);
  const b = await post(d.id, `${mention(a)} naming you`);
  await edit(a, `${mention(b.id)} naming you back, which closes a loop`);

  const page = await visit(browser, `/d/${d.id}`);
  const rows = await page.evaluate(readStream);
  check('the earlier post stays the root and the walk terminates', rows.map((r) => r.depth), [0, 1]);
  await page.close();
}

async function eviction(browser) {
  console.log('a parent the post stream has thrown away');

  // PostStreamState loads twenty posts at a time, and drops the pages left
  // behind only once it is more than two of them past them:
  //
  //   const twoPagesAway = start - PostStreamState.loadCount * 2;
  //   if (twoPagesAway > this.visibleStart && twoPagesAway >= 0) { ... }
  //
  // which means a thread has to be well past sixty posts before reading it from
  // the top drops anything at all — at sixty the sum never clears zero and the
  // whole discussion stays in memory. A shorter thread than this one would
  // prove nothing, however long the test spent scrolling it.
  //
  // So: a hundred and nine posts, with two replies at the foot of them. One
  // answers the second post, which by then is long gone; the other answers a
  // post twenty five back, which is still there. Reading to the end and finding
  // the two rendered differently is the check.
  const d = await discussion('e2e: eviction', 'root');
  const farAnchor = await post(d.id, 'the far parent, dropped long before the end');
  for (let i = 0; i < 80; i++) await post(d.id, `filler ${i + 1}`);
  const nearAnchor = await post(d.id, 'the near parent, still inside the window');
  for (let i = 0; i < 24; i++) await post(d.id, `filler ${81 + i}`);
  const farReply = await post(d.id, `${mention(farAnchor.id)} answering the far one`);
  const nearReply = await post(d.id, `${mention(nearAnchor.id)} answering the near one`);

  const page = await visit(browser, `/d/${d.id}`);

  // Walk to the foot of the thread. Scrolling loads each page on the way, which
  // is exactly the reading that leaves the near parent in memory and the far one
  // long gone.
  let atEnd = false;
  for (let i = 0; i < 120; i++) {
    await page.evaluate(() => window.scrollTo(0, document.body.scrollHeight));
    await sleep(400);
    atEnd = await page.evaluate(
      (n) => !!document.querySelector(`.PostStream-item[data-number="${n}"] article`),
      nearReply.attributes.number
    );
    if (atEnd) break;
  }

  if (!atEnd) {
    failures++;
    console.log('  FAIL  never reached the end of the thread');
    await page.close();
    return;
  }
  await sleep(1200);

  const state = await page.evaluate(
    ([farNo, nearNo, farAnchorNo, nearAnchorNo]) => {
      const stream = app.current.get('stream');
      const loaded = (n) => stream.posts().filter(Boolean).some((p) => p.number() === n);
      const depthOfRow = (n) => {
        const item = document.querySelector(`.PostStream-item[data-number="${n}"] article`);
        if (!item) return null;
        const found = Array.from(item.classList).find((c) => c.startsWith('itqanTree--depth'));
        return found ? Number(found.slice(-1)) : 0;
      };
      return {
        farAnchorLoaded: loaded(farAnchorNo),
        nearAnchorLoaded: loaded(nearAnchorNo),
        farDepth: depthOfRow(farNo),
        nearDepth: depthOfRow(nearNo),
      };
    },
    [
      farReply.attributes.number,
      nearReply.attributes.number,
      farAnchor.attributes.number,
      nearAnchor.attributes.number,
    ]
  );

  // Dropped from the window, but the store still has it, so the reply keeps its
  // shape. A post that re-flattened itself every time the reader scrolled far
  // enough away from its parent would be worse than one that does not.
  check('the far parent has been dropped from the window', state.farAnchorLoaded, false);
  check('its reply stays indented, because the store still has the parent', state.farDepth, 1);
  check('the near parent is still in the window', state.nearAnchorLoaded, true);
  check('and its reply is indented too', state.nearDepth, 1);
  await page.close();

  // The other way of arriving: straight to the end, so the early posts are never
  // fetched at all. This is the case with no parent to indent under, and on a
  // thread this long any landing near the foot leaves the far parent unasked
  // for, whichever post the stream settles on.
  const fresh = await browser.newPage();
  await fresh.setViewport({ width: 1000, height: 1400 });
  fresh.on('pageerror', (e) => {
    failures++;
    console.log('  FAIL  uncaught page error:', e.message);
  });
  await fresh.goto(`${FORUM}/d/${d.id}/${farReply.attributes.number}`, { waitUntil: 'networkidle2' });
  await fresh.waitForSelector('.PostStream-item article', { timeout: 20000 });
  await sleep(2000);

  // Where the stream settles after a jump varies, so walk the last stretch to
  // put the reply on screen. Scrolling down only ever asks for later posts, so
  // the far parent stays unfetched however far this goes.
  for (let i = 0; i < 40; i++) {
    const there = await fresh.evaluate(
      (n) => !!document.querySelector(`.PostStream-item[data-number="${n}"] article`),
      farReply.attributes.number
    );
    if (there) break;
    await fresh.evaluate(() => window.scrollTo(0, document.body.scrollHeight));
    await sleep(400);
  }
  await sleep(1000);

  const arrivedCold = await fresh.evaluate(
    ([farAnchorId, farNo]) => {
      const item = document.querySelector(`.PostStream-item[data-number="${farNo}"] article`);
      const depth = item
        ? Array.from(item.classList).find((c) => c.startsWith('itqanTree--depth'))
        : undefined;
      return {
        parentInStore: !!app.store.getById('posts', String(farAnchorId)),
        rendered: !!item,
        depth: item ? (depth ? Number(depth.slice(-1)) : 0) : null,
      };
    },
    [farAnchor.id, farReply.attributes.number]
  );

  check('landing at the end never fetches the far parent', arrivedCold.parentInStore, false);

  // Asserted, not skipped when the row is missing. An assertion that quietly
  // steps aside on a slow machine is one the suite can report as passing
  // without ever having made it, which is worse than not having written it.
  check('the reply is on screen to be judged', arrivedCold.rendered, true);
  check('so it renders flat rather than guessing', arrivedCold.depth, 0);

  await fresh.close();
}

async function phoneGeometry(browser) {
  console.log('indent on a phone, measured against core');

  const d = await discussion('e2e: phone', 'root');
  const root = await firstPostOf(d.id);
  const a = await post(d.id, `${mention(root)} first reply`);
  await post(d.id, `${mention(a.id)} reply to the reply`);

  const page = await visit(browser, `/d/${d.id}`, { width: 375, height: 900 });
  const rows = await page.evaluate(readStream);

  // Core gives every row but the last `margin: 0 -15px; padding: 0 15px` at this
  // width. The indent is measured from that, and the padding is left alone, so
  // the first step is one step rather than one step plus the bleed.
  check('each step is twelve pixels from core\'s bleed', rows.map((r) => r.margin), [
    '-15px', '-3px', '9px',
  ]);
  check('core padding survives on every row', rows.map((r) => r.padding), [
    '15px', '15px', '15px',
  ]);
  await page.close();
}

// ---------------------------------------------------------------- run ------

(async () => {
  token = (
    await api('/token', 'POST', { identification: ADMIN_USER, password: ADMIN_PASS })
  ).token;

  const browser = await puppeteer.launch({
    executablePath: CHROME,
    headless: 'new',
    args: ['--no-sandbox'],
  });

  try {
    await shapeAndCollapse(browser);
    await depthCap(browser);
    await mutualMention(browser);
    await eviction(browser);
    await phoneGeometry(browser);
  } finally {
    await browser.close();
  }

  if (checksRun !== EXPECTED_CHECKS) {
    failures++;
    console.log(`\n  FAIL  ${checksRun} checks ran, expected ${EXPECTED_CHECKS} — a case bailed out early`);
  }

  console.log(
    failures ? `\n${failures} check(s) failed` : `\nall ${checksRun} checks passed`
  );
  process.exit(failures ? 1 : 0);
})().catch((e) => {
  console.error('could not run:', e.message);
  process.exit(1);
});
