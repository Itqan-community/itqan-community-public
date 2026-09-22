import Modal from 'flarum/common/components/Modal';
import Button from 'flarum/common/components/Button';
import extractText from 'flarum/common/utils/extractText';
import app from 'flarum/forum/app';

export default class DeleteConfirmModal extends Modal {
  className() {
    return 'DeleteConfirmModal Modal--small';
  }

  title() {
    return this.attrs.title || extractText(app.translator.trans('core.forum.post_controls.delete_confirmation'));
  }

  content() {
    const { onconfirm, message, confirmLabel } = this.attrs;

    return m('.Modal-body', [
      m('p', message || extractText(app.translator.trans('core.forum.post_controls.delete_confirmation'))),
      m('.Modal-footer', [
        m(
          Button,
          {
            className: 'Button Button--primary Button--danger',
            onclick: () => {
              this.hide();
              if (onconfirm) onconfirm();
            },
          },
          confirmLabel || extractText(app.translator.trans('core.forum.post_controls.delete_button'))
        ),
        m(
          Button,
          {
            className: 'Button Button--link',
            onclick: () => this.hide(),
          },
          extractText(app.translator.trans('core.forum.composer_raw.cancel_button')) || 'Cancel'
        ),
      ]),
    ]);
  }
}
