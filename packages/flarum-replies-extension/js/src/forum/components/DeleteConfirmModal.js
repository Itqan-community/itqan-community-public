import Modal from 'flarum/common/components/Modal';
import Button from 'flarum/common/components/Button';
import extractText from 'flarum/common/utils/extractText';
import app from 'flarum/forum/app';

function resolveText(val, fallback) {
  if (!val) return fallback;
  if (typeof val === 'string') return val;
  try {
    const extracted = extractText(val);
    if (extracted && typeof extracted === 'string' && extracted.trim()) {
      return extracted;
    }
  } catch (e) {}
  return fallback;
}

export default class DeleteConfirmModal extends Modal {
  className() {
    return 'DeleteConfirmModal Modal--small';
  }

  title() {
    return resolveText(
      this.attrs.title,
      resolveText(app.translator.trans('core.forum.post_controls.delete_confirmation'), 'تأكيد الإجراء')
    );
  }

  content() {
    const { onconfirm, message, confirmLabel, cancelLabel } = this.attrs;

    const defaultMsg = resolveText(app.translator.trans('core.forum.post_controls.delete_confirmation'), 'هل أنت تأكد من إجراء هذا الحذف؟');
    const bodyMessage = resolveText(message, defaultMsg);

    const defaultConfirm = resolveText(app.translator.trans('core.forum.post_controls.delete_button'), 'تأكيد');
    const confirmText = resolveText(confirmLabel, defaultConfirm);

    const defaultCancel = resolveText(app.translator.trans('core.forum.composer.cancel_tooltip'), resolveText(app.translator.trans('core.lib.cancel'), 'إلغاء'));
    const cancelText = resolveText(cancelLabel, defaultCancel);

    return m('.Modal-body', [
      m('.DeleteConfirmModal-bodyText', bodyMessage),
      m('.Modal-footer', [
        m(
          Button,
          {
            className: 'Button Button--primary Button--danger DeleteConfirmModal-buttonConfirm',
            onclick: () => {
              this.hide();
              if (onconfirm) onconfirm();
            },
          },
          confirmText
        ),
        m(
          Button,
          {
            className: 'Button Button--cancel DeleteConfirmModal-buttonCancel',
            onclick: () => this.hide(),
          },
          cancelText
        ),
      ]),
    ]);
  }
}

