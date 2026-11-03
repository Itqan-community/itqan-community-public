import { extend } from 'flarum/common/extend';
import DiscussionListItem from 'flarum/forum/components/DiscussionListItem';
import HeaderSecondary from 'flarum/forum/components/HeaderSecondary';

/**
 * Core labels a discussion row's unread-count button with a phrase ("ردان غير
 * مقروءان") while the visible text is the digit itself. WCAG 2.5.3 requires the
 * accessible name to contain the visible label, otherwise voice control cannot
 * target it by what the reader sees (axe: label-content-name-mismatch).
 *
 * The number lives in an aria-hidden span inside the button; append it to the
 * label, once, and only when it is not already there.
 */
function alignCountLabel(root) {
  const button = root?.querySelector?.('.DiscussionListItem-count');

  if (!button) return;

  const visible = button.querySelector('.DiscussionListItem-stats-item-label span')?.textContent?.trim();
  const label = button.getAttribute('aria-label');

  if (!visible || !label || label.includes(visible)) return;

  button.setAttribute('aria-label', `${visible} · ${label}`);
}

/**
 * Same rule for the header bells: the notification and flag toggles show a
 * numeric bubble, so the number has to appear in the accessible name too.
 */
function alignBubbleLabel(root) {
  root?.querySelectorAll?.('.HeaderDropdown .Dropdown-toggle').forEach((button) => {
    const bubble = button.querySelector('.HeaderDropdownBubble')?.textContent?.trim();
    const label = button.getAttribute('aria-label');

    if (!bubble || !label || label.includes(bubble)) return;

    button.setAttribute('aria-label', `${label} (${bubble})`);
  });
}

export default function alignCountLabels() {
  extend(DiscussionListItem.prototype, 'oncreate', function () {
    requestAnimationFrame(() => alignCountLabel(this.element));
  });

  extend(DiscussionListItem.prototype, 'onupdate', function () {
    alignCountLabel(this.element);
  });

  extend(HeaderSecondary.prototype, ['oncreate', 'onupdate'], function () {
    alignBubbleLabel(this.element);
  });
}
