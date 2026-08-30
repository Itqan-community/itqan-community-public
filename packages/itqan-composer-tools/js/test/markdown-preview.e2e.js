/*
 * End-to-end checks for the live Markdown preview.
 *
 *   docker compose up -d
 *   npm test
 *
 * The test uses the real forum formatter and the installed Markdown table
 * extension. Set CHROME, FORUM, ADMIN_USER, or ADMIN_PASS to override the
 * local defaults.
 */
const puppeteer = require('puppeteer-core');
const fs = require('node:fs');

const FORUM = process.env.FORUM || 'http://localhost:8080';
const ADMIN_USER = process.env.ADMIN_USER || 'admin';
const ADMIN_PASS = process.env.ADMIN_PASS || 'password123';
const CHROME =
  process.env.CHROME ||
  [
    '/usr/bin/google-chrome',
    '/usr/bin/google-chrome-stable',
    '/usr/bin/chromium',
    '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome',
  ].find((path) => fs.existsSync(path));

const EXPECTED_CHECKS = 13;

let failures = 0;
let checksRun = 0;

function check(name, actual, expected) {
  checksRun++;

  if (JSON.stringify(actual) === JSON.stringify(expected)) {
    console.log(`  ok    ${name}`);
    return;
  }

  failures++;
  console.log(`  FAIL  ${name}\n          expected ${JSON.stringify(expected)}\n          actual   ${JSON.stringify(actual)}`);
}

async function login(page) {
  await page.goto(FORUM, { waitUntil: 'networkidle2' });
  await page.click('.Header-secondary .item-logIn button');
  await page.waitForSelector('.LogInModal input[name="identification"]', { visible: true });
  await page.type('.LogInModal input[name="identification"]', ADMIN_USER);
  await page.type('.LogInModal input[name="password"]', ADMIN_PASS);
  await Promise.all([
    page.waitForFunction(() => !document.querySelector('.LogInModal')),
    page.click('.LogInModal button[type="submit"]'),
  ]);
}

async function openComposer(page) {
  await page.waitForSelector('.IndexPage-newDiscussion:not([disabled])', { visible: true });
  await page.click('.IndexPage-newDiscussion');
  await page.waitForSelector('.ComposerBody--discussion .TextEditor-editor', { visible: true });

  await dismissAlerts(page);
}

async function dismissAlerts(page) {
  const dismissed = await page.$$eval('.Alert-dismiss', (buttons) => {
    buttons.forEach((button) => button.click());
    return buttons.length;
  });

  if (dismissed) {
    await page.waitForFunction(
      () => !Array.from(document.querySelectorAll('.Alert')).some((alert) => alert.getBoundingClientRect().width > 0)
    );
  }
}

async function run() {
  if (!CHROME) {
    throw new Error('Chrome was not found. Set CHROME to its executable path.');
  }

  const browser = await puppeteer.launch({
    executablePath: CHROME,
    headless: true,
    args: ['--no-sandbox'],
  });

  const page = await browser.newPage();
  page.on('pageerror', (error) => {
    failures++;
    console.log('  FAIL  uncaught page error:', error.message);
  });

  try {
    await page.setViewport({ width: 1200, height: 900 });
    await login(page);
    await openComposer(page);

    check('preview toggle is limited to the new-discussion composer', await page.$$('.MarkdownPreviewToggle').then((items) => items.length), 1);

    await dismissAlerts(page);
    await page.click('.MarkdownPreviewToggle');
    await page.waitForSelector('.MarkdownPreviewDrawer');

    const accessibility = await page.$eval('.MarkdownPreviewToggle', (button) => ({
      pressed: button.getAttribute('aria-pressed'),
      expanded: button.getAttribute('aria-expanded'),
      controls: button.getAttribute('aria-controls'),
    }));
    check('toggle exposes its pressed state', accessibility.pressed, 'true');
    check('toggle exposes its expanded state', accessibility.expanded, 'true');
    check('toggle identifies the preview region', accessibility.controls, 'itqan-markdown-preview');
    check('empty drafts have an explicit state', await page.$eval('.MarkdownPreviewDrawer-empty', (element) => !element.hidden), true);

    const markdown = [
      '# عنوان',
      '',
      '> اقتباس واضح',
      '',
      '| الاسم | القيمة |',
      '| --- | --- |',
      '| ألف | **واحد** |',
    ].join('\n');

    await page.type('.TextEditor-editor', markdown);
    await page.waitForSelector('.MarkdownPreviewDrawer-content table');
    await page.waitForSelector('.MarkdownPreviewDrawer-content blockquote');

    check('headings render', await page.$eval('.MarkdownPreviewDrawer-content h1', (element) => element.textContent.trim()), 'عنوان');
    check('blockquotes render', await page.$eval('.MarkdownPreviewDrawer-content blockquote', (element) => element.textContent.trim()), 'اقتباس واضح');
    check('tables render', await page.$eval('.MarkdownPreviewDrawer-content table tbody td', (element) => element.textContent.trim()), 'ألف');
    check('inline formatting renders inside tables', await page.$eval('.MarkdownPreviewDrawer-content table strong', (element) => element.textContent.trim()), 'واحد');

    await page.type('.TextEditor-editor', '\n\n## تحديث مباشر');
    await page.waitForFunction(
      () => Array.from(document.querySelectorAll('.MarkdownPreviewDrawer-content h2')).some((heading) => heading.textContent.includes('تحديث مباشر'))
    );
    check('preview updates while typing', true, true);

    const desktopState = await page.evaluate(() => ({
      editor: getComputedStyle(document.querySelector('.TextEditor-editorContainer')).display !== 'none',
      preview: getComputedStyle(document.querySelector('.MarkdownPreviewDrawer')).display !== 'none',
    }));
    check('desktop keeps editor and preview visible', desktopState, { editor: true, preview: true });

    await page.setViewport({ width: 390, height: 844 });
    const mobileState = await page.evaluate(() => ({
      editor: getComputedStyle(document.querySelector('.TextEditor-editorContainer')).display,
      preview: getComputedStyle(document.querySelector('.MarkdownPreviewDrawer')).display,
    }));
    check('phone switches to preview-only mode', mobileState.editor === 'none' && mobileState.preview !== 'none', true);

    await page.click('.MarkdownPreviewToggle');
    await page.waitForFunction(() => !document.querySelector('.MarkdownPreviewDrawer'));
    check('phone toggle returns to the editor', await page.$eval('.TextEditor-editorContainer', (element) => getComputedStyle(element).display !== 'none'), true);
  } finally {
    await page.close();
    await browser.close();
  }

  if (checksRun !== EXPECTED_CHECKS) {
    failures++;
    console.log(`  FAIL  expected ${EXPECTED_CHECKS} assertions, but ${checksRun} ran`);
  }

  if (failures) {
    console.error(`\n${failures} check(s) failed.`);
    process.exit(1);
  }

  console.log(`\n${checksRun} checks passed.`);
}

run().catch((error) => {
  console.error(error);
  process.exit(1);
});
