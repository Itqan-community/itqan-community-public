import app from 'flarum/forum/app';
import Notification from 'flarum/forum/components/Notification';
import { truncate } from 'flarum/common/utils/string';

/**
 * A private message in the bell.
 *
 * Upstream only ever sent DMs by email, so no component existed. The item links
 * straight to the conversation (deep link to the message) and previews its
 * text, matching how every other Itqan notification reads.
 */
export default class MessageReceivedNotification extends Notification {
  /** Dialog relationship, when the notification payload carried it. */
  dialogFor(notification) {
    const message = notification.subject();

    return message && typeof message.dialog === 'function' ? message.dialog() : false;
  }

  icon() {
    return 'fas fa-envelope';
  }

  href() {
    const dialog = this.dialogFor(this.attrs.notification);

    if (dialog) {
      return app.route('messages.dialog', { id: dialog.id() });
    }

    // The subject is included by the notifications endpoint, but its dialog is
    // not. Fetch it once, then re-render with the correct deep link instead of
    // dropping the reader on the inbox.
    this.loadDialog();

    return app.route('messages');
  }

  loadDialog() {
    const notification = this.attrs.notification;
    const message = notification.subject();

    if (!message || this.loadingDialog || message.dialog()) return;

    this.loadingDialog = true;

    app.store
      .find('dialog-messages', message.id(), { include: 'dialog' })
      .then(() => {
        this.loadingDialog = false;
        m.redraw();
      })
      .catch(() => {
        this.loadingDialog = false;
      });
  }

  content() {
    const user = this.attrs.notification.fromUser();

    return app.translator.trans('flarum-messages.forum.notifications.message_received_text', { user });
  }

  excerpt() {
    const message = this.attrs.notification.subject();

    return truncate((message && typeof message.contentPlain === 'function' && message.contentPlain()) || '', 200);
  }
}
