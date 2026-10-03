/*
 * Visual QA screenshot tool for the forum modernization programme.
 *
 * Captures one page at every breakpoint we design for, in both colour schemes,
 * so a redesign can be compared against a baseline rather than eyeballed once.
 *
 *   cd packages/itqan-discussions/js
 *   npm install --no-save puppeteer-core
 *   node test/shots.js --path=/d/247 --label=before
 *   node test/shots.js --path=/d/247 --label=after --scheme=dark
 *
 * Options
 *   --path      forum path to visit            (default /d/247)
 *   --label     folder name under shots/       (default "run")
 *   --scheme    light | dark | both            (default both)
 *   --widths    comma separated viewport list  (default 360,414,768,1024,1440)
 *   --full      capture full page height       (default viewport only)
 *   --anon      skip logging in
 *   --scroll    pixels to scroll before shot   (default 0)
 *   --to        selector to scroll into view   (wins over --scroll)
 *   --clip      selector to capture alone      (component-level QA)
 *   --click     selector to click before shot  (repeat with --click2 for a second)
 *   --hover     selector to hover before shot  (reveals hover-only affordances)
 *   --noclean   keep modals/toasts visible
 */
const fs = require('fs');
const os = require('os');
const path = require('path');
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
  process.env.CHROME ||
  (process.platform === 'win32'
    ? 'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe'
    : '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome');

const targetPath = arg('path', '/d/247');
const label = arg('label', 'run');
const schemeArg = arg('scheme', 'both');
const widths = String(arg('widths', '360,414,768,1024,1440'))
  .split(',')
  .map((w) => parseInt(w, 10))
  .filter(Boolean);
const fullPage = !!arg('full', false);
const anon = !!arg('anon', false);
const scrollBy = parseInt(arg('scroll', '0'), 10) || 0;
const scrollTo = arg('to', null);
const clip = arg('clip', null);
const clean = !arg('noclean', false);
const clickSelectors = [arg('click', null), arg('click2', null)].filter(Boolean);
const hoverSelector = arg('hover', null);

// Overlays that cover the layout under review. `#newsletter-popup-backdrop` and
// `.notify-popup` both sit at z-index 9999 and appear on a timer, so hiding them
// by class after load is the only reliable way to get a clean frame. They also
// swallow every synthetic click, so --click needs this too, not just the shots.
const CLEAN_CSS = `
  #newsletter-popup-backdrop,
  .notify-popup,
  .ModalManager, .Modal-backdrop,
  .AlertManager, .Alerts, .App-notices { display: none !important; }
  * { animation-play-state: paused !important; }
`;

const schemes = schemeArg === 'both' ? ['light', 'dark'] : [schemeArg];
// Outside the repo by default: the workspace ignore rules hide *.png from
// tooling that would otherwise read the results back.
const outDir = path.join(process.env.SHOTS_DIR || path.join(os.tmpdir(), 'itqan-shots'), label);

const sleep = (ms) => new Promise((r) => setTimeout(r, ms));

async function login(page) {
  await page.goto(`${FORUM}/`, { waitUntil: 'networkidle2', timeout: 60000 });
  await page.waitForFunction(() => window.app && window.app.session, { timeout: 30000 });

  // Flarum guards /login with a CSRF token that only the booted frontend holds.
  const result = await page.evaluate(
    async ([forum, identification, password]) => {
      const res = await fetch(`${forum}/login`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-Token': window.app.session.csrfToken,
        },
        credentials: 'include',
        body: JSON.stringify({ identification, password, remember: true }),
      });
      return { status: res.status, body: (await res.text()).slice(0, 200) };
    },
    [FORUM, USER, PASS]
  );
  if (result.status >= 400) throw new Error(`login failed: ${result.status} ${result.body}`);
}

(async () => {
  fs.mkdirSync(outDir, { recursive: true });

  const browser = await puppeteer.launch({
    executablePath: CHROME,
    headless: 'new',
    args: ['--no-sandbox', '--disable-setuid-sandbox', '--font-render-hinting=none'],
  });

  try {
    const page = await browser.newPage();
    page.on('pageerror', (e) => console.log(`  page error: ${e.message}`));

    if (!anon) {
      await login(page);
      console.log(`logged in as ${USER}`);
    }

    for (const scheme of schemes) {
      for (const width of widths) {
        await page.setViewport({ width, height: Math.round(width * 1.8), deviceScaleFactor: 1 });
        await page.goto(`${FORUM}${targetPath}`, { waitUntil: 'networkidle2', timeout: 60000 });

        await page.evaluate((s) => {
          document.documentElement.setAttribute('data-itqan-theme', s);
          try {
            localStorage.setItem('itqan-theme', s);
          } catch (e) {}
        }, scheme);

        await page.waitForSelector('.PostStream', { timeout: 30000 }).catch(() => {});
        await sleep(1800);

        if (clean) await page.addStyleTag({ content: CLEAN_CSS });

        if (scrollTo) {
          await page.evaluate((sel) => {
            const el = document.querySelector(sel);
            if (el) el.scrollIntoView({ block: 'start' });
          }, scrollTo);
          await sleep(900);
        } else if (scrollBy) {
          await page.evaluate((y) => window.scrollTo(0, y), scrollBy);
          await sleep(900);
        }

        for (const selector of clickSelectors) {
          const handle = await page.$(selector);
          if (!handle) {
            console.log(`  --click selector not found: ${selector}`);
            continue;
          }
          // Centre it first: puppeteer's own scroll can leave the target under
          // the fixed 52px header, which then swallows the click silently.
          await page.evaluate((sel) => {
            const el = document.querySelector(sel);
            if (el) el.scrollIntoView({ block: 'center' });
          }, selector);
          await sleep(400);
          await handle.click();
          await sleep(700);
        }

        if (hoverSelector) {
          const handle = await page.$(hoverSelector);
          if (handle) {
            await handle.hover();
            await sleep(400);
          } else {
            console.log(`  --hover selector not found: ${hoverSelector}`);
          }
        }

        const file = path.join(outDir, `${scheme}-${width}.png`);
        const target = clip ? await page.$(clip) : page;
        if (!target) throw new Error(`--clip selector not found: ${clip}`);
        await target.screenshot({ path: file, fullPage: clip ? undefined : fullPage });
        console.log(`  ${file}`);
      }
    }

    await page.close();
  } finally {
    await browser.close();
  }
})().catch((e) => {
  console.error(e);
  process.exit(1);
});
