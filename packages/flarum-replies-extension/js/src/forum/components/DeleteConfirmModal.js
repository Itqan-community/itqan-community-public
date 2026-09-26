import Modal from 'flarum/common/components/Modal';
import Button from 'flarum/common/components/Button';
import app from 'flarum/forum/app';

/**
 * Confirmation modal used for the destructive post actions (hide, delete
 * forever) and for discarding an unsaved inline reply draft.
 *
 * Labels are passed in as Mithril children (the result of
 * `app.translator.trans(...)`), never as raw translation keys: `trans()`
 * returns the key itself when it is missing, so a `.foo.bar` string would
 * otherwise leak straight into the UI. Callers are responsible for supplying
 * keys that actually exist (see locale/en.yml and locale/ar.yml).
 */
export default class DeleteConfirmModal extends Modal {
  className() {
    return 'DeleteConfirmModal Modal--small';
  }

  title() {
    return this.attrs.title || app.translator.trans('core.forum.post_controls.delete_confirmation');
  }

  content() {
    const { message, confirmLabel, cancelLabel } = this.attrs;

    return [
      m('.Modal-body', m('.DeleteConfirmModal-bodyText', message || app.translator.trans('core.ref.generic_confirmation_message'))),
      m('.Modal-footer', [
        m(
          Button,
          {
            type: 'button',
            className: 'Button Button--link DeleteConfirmModal-buttonCancel',
            onclick: () => this.hide(),
          },
          cancelLabel || app.translator.trans('mtareq-nested-replies.forum.reply_form_cancel')
        ),
        m(
          Button,
          {
            type: 'submit',
            className: 'Button Button--danger DeleteConfirmModal-buttonConfirm',
          },
          confirmLabel || app.translator.trans('core.ref.delete')
        ),
      ]),
    ];
  }

  onsubmit(event) {
    event.preventDefault();
    this.confirm();
  }

  confirm() {
    this.hide();
    if (typeof this.attrs.onconfirm === 'function') this.attrs.onconfirm();
  }
}
