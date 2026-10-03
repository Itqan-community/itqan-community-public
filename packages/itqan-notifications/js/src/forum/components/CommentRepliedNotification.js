import app from 'flarum/forum/app';
import Notification from 'flarum/forum/components/Notification';
import { truncate } from 'flarum/common/utils/string';

/**
 * "Someone replied to your comment" — the notification's subject is the new
 * reply post (so the push notification and this deep-link both point at it).
 */
export default class CommentRepliedNotification extends Notification {
  icon() {
    return 'fas fa-reply';
  }

  href() {
    const post = this.attrs.notification.subject();
    const discussion = post && post.discussion ? post.discussion() : null;

    if (discussion && post && typeof post.number === 'function') {
      return app.route.discussion(discussion, post.number());
    }

    return app.forum.attribute('basePath') || '/';
  }

  /**
   * The base `Notification` view already wraps this in `.Notification-content`
   * and renders the timestamp, so return only the translated label.
   */
  content() {
    return app.translator.trans('itqan-notifications.forum.notifications.comment_replied_text', {
      user: this.attrs.notification.fromUser(),
    });
  }

  excerpt() {
    const post = this.attrs.notification.subject();

    return truncate((post && post.contentPlain && post.contentPlain()) || '', 200);
  }
}