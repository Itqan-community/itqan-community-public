/*
 * Structural audit of a rendered page: overlay elements, post markup, and the
 * computed geometry/typography that a redesign has to replace.
 *
 *   MSYS_NO_PATHCONV=1 node test/audit.js --path=/d/734
 */
const puppeteer = require('puppeteer-core');

const arg = (name, fallback) => {
  const hit = process.argv.find((a) => a.startsWith(`--${name}=`));
  if (hit) return hit.split('=').slice(1).join('=');
  return process.argv.includes(`--${name}`) ? true : fallback;
};

const FORUM = process.env.FORUM || 'http://localhost:8080';
const USER = process.env.ADMIN_USER || 'Amr-Bendary';
const PASS = process.env.ADMIN_PASS || 'LocalTest123!';
const CHROME =
  process.env.CHROME || 'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe';
const targetPath = arg('path', '/d/734');
const width = parseInt(arg('width', '1440'), 10);

const sleep = (ms) => new Promise((r) => setTimeout(r, ms));

(async () => {
  const browser = await puppeteer.launch({
    executablePath: CHROME,
    headless: 'new',
    args: ['--no-sandbox', '--disable-setuid-sandbox'],
  });
  const page = await browser.newPage();
  await page.setViewport({ width, height: 1200 });
  page.on('pageerror', (e) => console.error(`page error: ${e.message}`));
  page.on('console', (msg) => {
    if (msg.type() === 'error') console.error(`console error: ${msg.text()}`);
  });

  await page.goto(`${FORUM}/`, { waitUntil: 'networkidle2', timeout: 60000 });
  await page.waitForFunction(() => window.app && window.app.session, { timeout: 30000 });
  await page.evaluate(
    async ([forum, identification, password]) => {
      await fetch(`${forum}/login`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': window.app.session.csrfToken },
        credentials: 'include',
        body: JSON.stringify({ identification, password, remember: true }),
      });
    },
    [FORUM, USER, PASS]
  );

  await page.goto(`${FORUM}${targetPath}`, { waitUntil: 'networkidle2', timeout: 60000 });
  await page.waitForSelector('.PostStream', { timeout: 30000 });
  await sleep(4000);

  // The newsletter popup lays a full-viewport backdrop at z-index 9999 on a
  // timer. It intercepts every click below it, so an interaction audit measures
  // nothing at all unless it is removed first. Same list as shots.js.
  await page.addStyleTag({
    content: `
      #newsletter-popup-backdrop,
      .notify-popup,
      .ModalManager, .Modal-backdrop,
      .AlertManager, .Alerts, .App-notices { display: none !important; }
    `,
  });

  // Optional interaction, so collapse/expand can be measured rather than eyeballed.
  const clickSelector = arg('click', null);
  if (clickSelector) {
    const handle = await page.$(clickSelector);
    if (handle) {
      // Centre it first: puppeteer's own scroll can leave the target under the
      // fixed 52px header, which then swallows the click silently.
      await page.evaluate((sel) => {
        const el = document.querySelector(sel);
        if (el) el.scrollIntoView({ block: 'center' });
      }, clickSelector);
      await sleep(500);
      await handle.click();
      await sleep(900);
    } else {
      console.error(`--click selector not found: ${clickSelector}`);
    }
  }

  const report = await page.evaluate(() => {
    const out = {};

    // What floats above the page and therefore ruins a screenshot.
    out.overlays = [...document.body.children]
      .map((el) => ({
        tag: el.tagName.toLowerCase(),
        id: el.id || null,
        cls: el.className && el.className.toString().slice(0, 80),
        pos: getComputedStyle(el).position,
        display: getComputedStyle(el).display,
        z: getComputedStyle(el).zIndex,
        h: Math.round(el.getBoundingClientRect().height),
      }))
      .filter((e) => e.h > 0);

    out.fixed = [...document.querySelectorAll('body *')]
      .filter((el) => {
        const s = getComputedStyle(el);
        return (s.position === 'fixed' || s.position === 'sticky') && el.getBoundingClientRect().height > 4;
      })
      .slice(0, 25)
      .map((el) => ({
        sel: `${el.tagName.toLowerCase()}.${(el.className || '').toString().split(' ').filter(Boolean).slice(0, 3).join('.')}`,
        pos: getComputedStyle(el).position,
        z: getComputedStyle(el).zIndex,
        rect: (({ x, y, width, height }) => ({ x: Math.round(x), y: Math.round(y), w: Math.round(width), h: Math.round(height) }))(el.getBoundingClientRect()),
      }));

    // Layout columns.
    const box = (sel) => {
      const el = document.querySelector(sel);
      if (!el) return null;
      const r = el.getBoundingClientRect();
      const s = getComputedStyle(el);
      return {
        w: Math.round(r.width),
        h: Math.round(r.height),
        x: Math.round(r.x),
        pad: `${s.paddingTop} ${s.paddingRight} ${s.paddingBottom} ${s.paddingLeft}`,
        font: `${s.fontSize}/${s.lineHeight} ${s.fontWeight}`,
        color: s.color,
        bg: s.backgroundColor,
        border: s.borderTopWidth + ' ' + s.borderTopColor,
        radius: s.borderRadius,
        shadow: s.boxShadow,
      };
    };

    out.layout = {};
    for (const sel of [
      '.App-content',
      '.DiscussionPage-nav',
      '.DiscussionPage-discussion',
      '.PostStream',
      '.itqan-comments-card',
      '.Hero.DiscussionHero',
      '.DiscussionHero-title',
      '.PostStream-item[data-number="1"] .CommentPost',
      '.PostStream-item[data-thread-depth="0"] .CommentPost',
    ]) {
      out.layout[sel] = box(sel);
    }

    // Depth distribution actually rendered.
    const items = [...document.querySelectorAll('.PostStream-item')];
    out.depths = {};
    items.forEach((el) => {
      const d = el.getAttribute('data-thread-depth') || 'none';
      out.depths[d] = (out.depths[d] || 0) + 1;
    });
    out.itemCount = items.length;

    // Indentation actually applied per depth, and remaining text width.
    out.indent = {};
    items.forEach((el) => {
      const d = el.getAttribute('data-thread-depth');
      if (d == null || out.indent[d]) return;
      const inner = el.querySelector('.CommentPost > div');
      const body = el.querySelector('.Post-body');
      if (!inner) return;
      const s = getComputedStyle(inner);
      out.indent[d] = {
        padInlineStart: s.paddingInlineStart,
        padInlineEnd: s.paddingInlineEnd,
        bodyWidth: body ? Math.round(body.getBoundingClientRect().width) : null,
      };
    });

    // Rail alignment: a rail must sit on its ancestor's avatar centre. Anything
    // else means the indent scale and the rail offsets have drifted apart.
    out.railAlignment = [...document.querySelectorAll('.itqan-thread-rail[data-rail-ancestor-id]')]
      .slice(0, 6)
      .map((rail) => {
        const ancestorId = rail.getAttribute('data-rail-ancestor-id');
        const ancestorRow = document.querySelector(`.PostStream-item[data-id="${ancestorId}"]`);
        const avatarEl = ancestorRow ? ancestorRow.querySelector('.PostUser-avatar, .Avatar') : null;
        const railRect = rail.getBoundingClientRect();
        const railCentre = railRect.x + railRect.width / 2;
        const avatarCentre = avatarEl
          ? avatarEl.getBoundingClientRect().x + avatarEl.getBoundingClientRect().width / 2
          : null;
        return {
          col: rail.getAttribute('data-rail-col'),
          railCentre: Math.round(railCentre),
          avatarCentre: avatarCentre == null ? null : Math.round(avatarCentre),
          drift: avatarCentre == null ? null : Math.round(railCentre - avatarCentre),
          visible: railRect.width > 0 && railRect.height > 0,
        };
      });

    out.railCount = document.querySelectorAll('.itqan-thread-rail').length;
    out.railHitCount = document.querySelectorAll('.itqan-thread-rail-hit').length;

    // The action bar: what buttons exist, in what order, at what size.
    const post = document.querySelector('.PostStream-item .CommentPost .Post-actions');
    out.actions = post
      ? [...post.querySelectorAll(':scope > ul > li')].map((li) => {
          const r = li.getBoundingClientRect();
          return {
            cls: (li.className || '').toString().slice(0, 60),
            text: (li.innerText || '').trim().slice(0, 24),
            x: Math.round(r.x),
            right: Math.round(r.right),
            w: Math.round(r.width),
            h: Math.round(r.height),
          };
        })
      : null;
    out.actionsBox = box('.PostStream-item .CommentPost .Post-actions');

    // Is the action row's first item flush with the text it acts on? In RTL the
    // inline-start edge is the right edge, so compare `right` values.
    const refRow = post ? post.closest('.PostStream-item') : null;
    const refBody = refRow ? refRow.querySelector('.Post-body') : null;
    const refPara = refBody ? refBody.querySelector('p') : refBody;
    const firstAction = post ? post.querySelector(':scope > ul > li') : null;
    out.alignment = {
      dir: getComputedStyle(document.documentElement).direction,
      bodyRight: refBody ? Math.round(refBody.getBoundingClientRect().right) : null,
      bodyLeft: refBody ? Math.round(refBody.getBoundingClientRect().left) : null,
      firstActionRight: firstAction ? Math.round(firstAction.getBoundingClientRect().right) : null,
      firstActionLeft: firstAction ? Math.round(firstAction.getBoundingClientRect().left) : null,
      paraWidth: refPara ? Math.round(refPara.getBoundingClientRect().width) : null,
      paraMaxWidth: refPara ? getComputedStyle(refPara).maxWidth : null,
    };

    // Widest rendered paragraph anywhere in the stream — the real measure check.
    const paraWidths = [...document.querySelectorAll('.CommentPost .Post-body > p')]
      .map((p) => Math.round(p.getBoundingClientRect().width))
      .filter((w) => w > 0);
    out.measure = {
      widestParagraph: paraWidths.length ? Math.max(...paraWidths) : null,
      paragraphs: paraWidths.length,
    };

    // Header composition.
    const header = document.querySelector('.PostStream-item .CommentPost .Post-header');
    out.header = header
      ? [...header.querySelectorAll(':scope > ul > li')].map((li) => ({
          cls: (li.className || '').toString().slice(0, 50),
          text: (li.innerText || '').trim().slice(0, 30),
          w: Math.round(li.getBoundingClientRect().width),
        }))
      : null;

    // Rails.
    out.rails = [...document.querySelectorAll('.itqan-thread-rail')].slice(0, 8).map((el) => {
      const r = el.getBoundingClientRect();
      return { x: Math.round(r.x), w: Math.round(r.width), h: Math.round(r.height), col: el.style.getPropertyValue('--rail-col') };
    });

    // Horizontal overflow — a classic nesting failure.
    out.overflow = {
      docScrollW: document.documentElement.scrollWidth,
      clientW: document.documentElement.clientWidth,
      wide: [...document.querySelectorAll('.PostStream-item *')]
        .filter((el) => el.getBoundingClientRect().right > document.documentElement.clientWidth + 2)
        .slice(0, 8)
        .map((el) => `${el.tagName.toLowerCase()}.${(el.className || '').toString().split(' ')[0]} right=${Math.round(el.getBoundingClientRect().right)}`),
    };

    // Collapse state: are rows actually hidden, and does the summary render?
    out.collapse = {
      registrySize: window.app && window.app.itqanCollapsedThreads ? window.app.itqanCollapsedThreads.size : null,
      registry: window.app && window.app.itqanCollapsedThreads ? [...window.app.itqanCollapsedThreads] : null,
      togglesTotal: document.querySelectorAll('.itqan-thread-toggle').length,
      collapsed: document.querySelectorAll('.itqan-thread-toggle[aria-expanded="false"]').length,
      hiddenRows: document.querySelectorAll('.PostStream-item[hidden]').length,
      summaryMarkup: (() => {
        const el = document.querySelector('.itqan-thread-toggle[aria-expanded="false"]');
        if (!el) return null;
        const r = el.getBoundingClientRect();
        return {
          text: (el.innerText || '').trim(),
          avatars: el.querySelectorAll('.itqan-avatar-stack .Avatar').length,
          hasTime: !!el.querySelector('.itqan-thread-toggle-time'),
          w: Math.round(r.width),
          h: Math.round(r.height),
        };
      })(),
    };

    // Token surface currently available.
    const rootStyle = getComputedStyle(document.documentElement);
    out.tokens = {};
    for (const name of [
      '--primary-color', '--body-bg', '--body-bg-light', '--body-bg-shaded', '--text-color',
      '--muted-color', '--control-bg', '--control-bg-shaded', '--heading-color', '--link-color',
      '--border-radius', '--shadow-color', '--switch-on-color', '--switch-off-color',
    ]) {
      out.tokens[name] = rootStyle.getPropertyValue(name).trim() || null;
    }
    out.customPropCount = null;
    out.fontFamily = rootStyle.fontFamily;
    out.dir = document.documentElement.getAttribute('dir');

    return out;
  });

  console.log(JSON.stringify(report, null, 2));
  await browser.close();
})().catch((e) => {
  console.error(e);
  process.exit(1);
});
