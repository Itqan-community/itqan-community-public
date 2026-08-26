import app from 'flarum/forum/app';

/**
 * The three things a reader can ask for. `auto` is not a colour scheme — it
 * defers to whatever the operating system is currently set to, and keeps
 * following it for as long as it is selected.
 */
export const MODES = ['light', 'dark', 'auto'];

export const STORAGE_KEY = 'itqan-theme';

const ATTRIBUTE = 'data-itqan-theme';
const TRANSITION_CLASS = 'itqan-theme-animating';
const PREFERENCE = 'itqanTheme';

const darkQuery = typeof window.matchMedia === 'function' ? window.matchMedia('(prefers-color-scheme: dark)') : null;

let transitionTimeout = null;

/**
 * Private browsing modes and locked-down profiles make `localStorage` throw on
 * access rather than return null, so every touch of it is guarded.
 */
function readStorage() {
  try {
    return localStorage.getItem(STORAGE_KEY);
  } catch (e) {
    return null;
  }
}

function writeStorage(mode) {
  try {
    localStorage.setItem(STORAGE_KEY, mode);
  } catch (e) {
    // A reader who cannot persist anything still gets the scheme they picked
    // for this page view; there is nothing to recover from here.
  }
}

export function isValid(mode) {
  return MODES.indexOf(mode) !== -1;
}

/**
 * The forum-wide starting point, for readers who have never chosen.
 */
export function defaultMode() {
  const configured = app.forum.attribute('itqanThemeDefault');

  return isValid(configured) ? configured : 'auto';
}

/**
 * A signed-in reader's choice lives on their account, so it follows them
 * between devices. A guest's lives in this browser.
 */
export function currentMode() {
  const user = app.session.user;

  if (user) {
    const preference = user.preferences()?.[PREFERENCE];

    if (isValid(preference)) return preference;
  }

  const stored = readStorage();

  return isValid(stored) ? stored : defaultMode();
}

export function systemScheme() {
  return darkQuery && darkQuery.matches ? 'dark' : 'light';
}

export function resolve(mode) {
  return mode === 'auto' ? systemScheme() : mode;
}

/**
 * Keeps the browser chrome (address bar on Android, status bar in the installed
 * PWA) in step with the page, so the seam at the top of the screen does not
 * stay light while everything below it turns dark.
 */
function syncBrowserChrome() {
  const meta = document.querySelector('meta[name="theme-color"]');

  if (!meta) return;

  const headerBg = getComputedStyle(document.documentElement).getPropertyValue('--header-bg').trim();

  if (headerBg) meta.setAttribute('content', headerBg);
}

/**
 * @param {string} mode one of MODES
 * @param {boolean} animate whether to cross-fade — false on first paint, where
 *   there is no previous colour to fade from
 */
export function apply(mode, animate = false) {
  const scheme = resolve(mode);

  if (animate) {
    document.documentElement.classList.add(TRANSITION_CLASS);

    clearTimeout(transitionTimeout);
    transitionTimeout = setTimeout(() => document.documentElement.classList.remove(TRANSITION_CLASS), 250);
  }

  document.documentElement.setAttribute(ATTRIBUTE, scheme);
  syncBrowserChrome();
}

export function setMode(mode) {
  if (!isValid(mode)) return Promise.resolve();

  apply(mode, true);

  // Written locally either way: it is what the inline boot script reads on the
  // next page load, before any of this JavaScript exists.
  writeStorage(mode);

  const user = app.session.user;

  if (user) {
    return user.savePreferences({ [PREFERENCE]: mode });
  }

  return Promise.resolve();
}

/**
 * Applies the stored choice and starts following the system while `auto` is
 * selected. The scheme is already on the element by the time this runs — the
 * inline boot script put it there — so this only matters when the two disagree,
 * which happens for a signed-in reader whose preference changed elsewhere.
 */
export function boot() {
  apply(currentMode());

  if (!darkQuery) return;

  const onSystemChange = () => {
    if (currentMode() === 'auto') {
      apply('auto', true);
      m.redraw();
    }
  };

  // Safari only gained `addEventListener` on MediaQueryList in 14.
  if (typeof darkQuery.addEventListener === 'function') {
    darkQuery.addEventListener('change', onSystemChange);
  } else if (typeof darkQuery.addListener === 'function') {
    darkQuery.addListener(onSystemChange);
  }
}
