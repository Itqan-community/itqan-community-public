/**
 * Keep the browser's own chrome (Android address bar, installed-PWA status bar)
 * in step with the active scheme.
 *
 * Core writes `theme-color` once from the primary colour, so in dark mode the
 * strip above the page stayed brand-green while the page itself was near-black.
 * Recompute it from the live header background whenever `data-theme` changes.
 */
export default function syncThemeColor() {
  const meta = document.querySelector('meta[name="theme-color"]');

  if (!meta) return;

  const update = () => {
    const styles = getComputedStyle(document.documentElement);
    const colour = styles.getPropertyValue('--header-bg').trim() || styles.getPropertyValue('--body-bg').trim();

    if (colour) meta.setAttribute('content', colour);
  };

  update();
  new MutationObserver(update).observe(document.documentElement, {
    attributes: true,
    attributeFilter: ['data-theme'],
  });
}
