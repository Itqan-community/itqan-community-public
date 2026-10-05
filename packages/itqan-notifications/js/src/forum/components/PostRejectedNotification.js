import app from 'flarum/forum/app';
import Notification from 'flarum/forum/components/Notification';

export default class PostRejectedNotification extends Notification {
  icon() {
    return 'fas fa-exclamation-triangle';
  }

  href() {
    return app.forum.attribute('basePath') || '/';
  }

  content() {
    const notification = this.attrs.notification;
    const post = notification.subject();
    const discussion = post && post.discussion ? post.discussion() : null;
    const title = discussion && typeof discussion.title === 'function' ? discussion.title() : '';

    return app.translator.trans('itqan-notifications.forum.notifications.post_rejected_text', { title });
  }
}
