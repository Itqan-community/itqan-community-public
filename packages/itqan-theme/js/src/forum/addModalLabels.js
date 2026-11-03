/**
 * Core's ModalManager wraps every modal in `role="dialog" aria-modal="true"`
 * but never names it, so a screen reader announces only "dialog" (axe:
 * aria-dialog-name). The modal itself has a visible title, so copy that onto
 * the wrapper. Runs from a MutationObserver because the manager is rendered
 * outside the components we own and its contents change per modal.
 */
function labelManager(manager) {
  if (manager.hasAttribute('aria-label') || manager.hasAttribute('aria-labelledby')) return;

  const title = manager.querySelector('.Modal-header h3, .Modal-header .App-titleControl, .Modal-title');
  const text = title?.textContent?.trim();

  if (text) manager.setAttribute('aria-label', text);
}

export default function addModalLabels() {
  const root = document.getElementById('modal');

  if (!root || typeof MutationObserver === 'undefined') return;

  const scan = () => root.querySelectorAll('.ModalManager[role="dialog"]').forEach(labelManager);

  scan();
  // Attributes are not observed on purpose: setting aria-label would retrigger
  // the callback. Child/text changes cover a new modal replacing the old one.
  new MutationObserver(scan).observe(root, { childList: true, subtree: true, characterData: true });
}
