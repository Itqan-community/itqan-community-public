import app from 'flarum/forum/app';
import Notification from 'flarum/forum/components/Notification';
import { truncate } from 'flarum/common/utils/string';

export default class PostApprovedNotification extends Notification {
  icon() {
    return 'fas fa-check-circle';
  }

  href() {
    const post = this.attrs.notification.subject();
    const discussion = post && post.discussion ? post.discussion() : null;

    if (discussion && post && typeof post.number === 'function') {
      return app.route.discussion(discussion, post.number());
    }

    return app.forum.attribute('basePath') || '/';
  }

  content() {
    const notification = this.attrs.notification;
    const post = notification.subject();
    const discussion = post && post.discussion ? post.discussion() : null;
    const title = discussion && typeof discussion.title === 'function' ? discussion.title() : '';

    return app.translator.trans('itqan-notifications.forum.notifications.post_approved_text', { title });
  }

  excerpt() {
    const post = this.attrs.notification.subject();
    return truncate((post && post.contentPlain && post.contentPlain()) || '', 200);
  }
}
