import app from 'flarum/forum/app';
import Notification from 'flarum/forum/components/Notification';
import { truncate } from 'flarum/common/utils/string';

/**
 * A private message in the bell.
 *
 * Upstream sends DMs by email only and never writes an in-app notification, so
 * no component existed. The row is created by our own alertable blueprint,
 * which stores the dialog and message ids in the notification payload — the
 * item can therefore link straight to the message without an extra request.
 */
export default class MessageAlertedNotification extends Notification {
  icon() {
    return 'fas fa-envelope';
  }

  href() {
    const data = this.attrs.notification.content() || {};

    if (data.dialogId) {
      return app.route('messages.dialog', { id: data.dialogId });
    }

    return app.route('messages');
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
