import Modal from 'flarum/common/components/Modal';
import Button from 'flarum/common/components/Button';
import app from 'flarum/forum/app';

/**
 * Confirmation dialog for destructive post actions.
 * All copy comes from core translation keys or this extension's own keys —
 * never hardcoded strings, so ar/en both resolve through Flarum's locale system.
 *
 * Cancel default note: core ships no `cancel` key at all (verified against the
 * installed vendor ymls — no `cancel`/`Cancel` match), and the previously used
 * `core.forum.composer.cancel_tooltip` doesn't exist either (resolveText used
 * to mask that by echoing the raw key). The default therefore uses this
 * extension's own `action_cancel` (locale/en.yml + locale/ar.yml).
 */
export default class DeleteConfirmModal extends Modal {
  className() {
    return 'DeleteConfirmModal Modal--small';
  }

  title() {
    return this.attrs.title || app.translator.trans('core.forum.post_controls.delete_confirmation');
  }

  content() {
    const { onconfirm, message, confirmLabel, cancelLabel } = this.attrs;

    const bodyMessage = message || app.translator.trans('core.forum.post_controls.delete_confirmation');
    const confirmText = confirmLabel || app.translator.trans('core.forum.post_controls.delete_button');
    const cancelText = cancelLabel || app.translator.trans('mtareq-nested-replies.forum.action_cancel');

    // Structure (bodyText class + Modal-footer + button classes) is kept
    // verbatim from the previous version: less/forum.less styles all three,
    // including its dark-mode variants.
    return m('.Modal-body', [
      m('.DeleteConfirmModal-bodyText', bodyMessage),
      m('.Modal-footer', [
        m(
          Button,
          {
            className: 'Button Button--danger DeleteConfirmModal-buttonConfirm',
            loading: this.loading,
            onclick: () => {
              // Run the confirmation first and only close on success: all call
              // sites return void (fire-and-forget promises), so this resolves
              // on the next microtask — same net timing as hide-first — while
              // promise-returning callers get a spinner until they settle.
              this.loading = true;
              Promise.resolve(typeof onconfirm === 'function' ? onconfirm() : null)
                .then(() => this.hide())
                .catch(() => {
                  this.loading = false;
                  m.redraw();
                });
            },
          },
          confirmText
        ),
        m(
          Button,
          {
            className: 'Button Button--default DeleteConfirmModal-buttonCancel',
            onclick: () => this.hide(),
          },
          cancelText
        ),
      ]),
    ]);
  }
}
