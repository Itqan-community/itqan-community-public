import Modal from 'flarum/common/components/Modal';
import Button from 'flarum/common/components/Button';
import extractText from 'flarum/common/utils/extractText';
import app from 'flarum/forum/app';

export default class DeleteConfirmModal extends Modal {
  className() {
    return 'DeleteConfirmModal Modal--small';
  }

  title() {
    return this.attrs.title || extractText(app.translator.trans('core.forum.post_controls.delete_confirmation')) || 'تأكيد الإجراء';
  }

  content() {
    const { onconfirm, message, confirmLabel, cancelLabel } = this.attrs;

    const cancelText = cancelLabel || extractText(app.translator.trans('core.forum.composer.cancel_tooltip')) || extractText(app.translator.trans('core.lib.cancel')) || 'إلغاء';

    return m('.Modal-body', [
      m('.DeleteConfirmModal-bodyText', message || extractText(app.translator.trans('core.forum.post_controls.delete_confirmation')) || 'هل أنت تأكد من إجراء هذا الحذف؟'),
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
          confirmLabel || extractText(app.translator.trans('core.forum.post_controls.delete_button')) || 'تأكيد'
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

