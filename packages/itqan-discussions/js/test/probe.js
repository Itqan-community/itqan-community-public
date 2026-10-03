// Throwaway diagnostic: what actually receives the collapse-toggle click?
const puppeteer = require('puppeteer-core');
const FORUM = 'http://localhost:8080';
const sleep = (ms) => new Promise((r) => setTimeout(r, ms));

(async () => {
  const b = await puppeteer.launch({
    executablePath: 'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe',
    headless: 'new',
    args: ['--no-sandbox'],
  });
  const p = await b.newPage();
  await p.setViewport({ width: 1440, height: 1200 });
  p.on('pageerror', (e) => console.log('PAGEERR', e.message));
  p.on('console', (m) => {
    if (m.type() === 'error') console.log('CONSOLE', m.text());
  });

  await p.goto(FORUM + '/', { waitUntil: 'networkidle2' });
  await p.waitForFunction(() => window.app && window.app.session);
  await p.evaluate(async (f) => {
    await fetch(f + '/login', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': window.app.session.csrfToken },
      credentials: 'include',
      body: JSON.stringify({ identification: 'Amr-Bendary', password: 'LocalTest123!', remember: true }),
    });
  }, FORUM);

  await p.goto(FORUM + '/d/734', { waitUntil: 'networkidle2' });
  await p.waitForSelector('.itqan-thread-toggle', { timeout: 30000 });
  await sleep(4000);

  const info = await p.evaluate(() => {
    const el = document.querySelector('.itqan-thread-toggle');
    el.scrollIntoView({ block: 'center' });
    const r = el.getBoundingClientRect();
    const cx = r.x + r.width / 2;
    const cy = r.y + r.height / 2;
    const hit = document.elementFromPoint(cx, cy);
    return {
      rect: { x: Math.round(r.x), y: Math.round(r.y), w: Math.round(r.width), h: Math.round(r.height) },
      hit: hit ? hit.tagName + '.' + String(hit.className).slice(0, 80) : null,
      isSelfOrChild: hit ? el === hit || el.contains(hit) : false,
      hasOnclick: !!el.onclick,
      registryExists: !!window.app.itqanCollapsedThreads,
      outer: el.outerHTML.slice(0, 240),
    };
  });
  console.log('probe:', JSON.stringify(info, null, 1));

  const stack = await p.evaluate(() => {
    const el = document.querySelector('.itqan-thread-toggle');
    const r = el.getBoundingClientRect();
    const els = document.elementsFromPoint(r.x + r.width / 2, r.y + r.height / 2);
    return els.slice(0, 6).map((n) => {
      const cs = getComputedStyle(n);
      const nr = n.getBoundingClientRect();
      return {
        sel: n.tagName + (n.className ? '.' + String(n.className).trim().replace(/\s+/g, '.') : '') + (n.id ? '#' + n.id : ''),
        pos: cs.position,
        z: cs.zIndex,
        pe: cs.pointerEvents,
        box: [Math.round(nr.x), Math.round(nr.y), Math.round(nr.width), Math.round(nr.height)],
      };
    });
  });
  console.log('stack:', JSON.stringify(stack, null, 1));

  const after = await p.evaluate(() => {
    document.querySelector('.itqan-thread-toggle').click();
    return { size: window.app.itqanCollapsedThreads.size };
  });
  console.log('after el.click():', JSON.stringify(after));

  await sleep(1200);
  const state = await p.evaluate(() => ({
    size: window.app.itqanCollapsedThreads.size,
    registry: [...window.app.itqanCollapsedThreads],
    hidden: document.querySelectorAll('.PostStream-item[hidden]').length,
    collapsedAttr: document.querySelectorAll('.itqan-thread-toggle[aria-expanded="false"]').length,
  }));
  console.log('state:', JSON.stringify(state));

  await b.close();
})().catch((e) => {
  console.error(e);
  process.exit(1);
});
