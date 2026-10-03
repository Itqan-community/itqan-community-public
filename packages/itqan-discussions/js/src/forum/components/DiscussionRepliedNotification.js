import app from 'flarum/forum/app';
import Notification from 'flarum/forum/components/Notification';
import { truncate } from 'flarum/common/utils/string';

export default class DiscussionRepliedNotification extends Notification {
  icon() {
    return 'fas fa-reply';
  }

  href() {
    const post = this.attrs.notification.subject();
    const discussion = post && post.discussion ? post.discussion() : null;

    // Deep-link to the new reply. Falls back to base path if anything is missing.
    if (discussion && post && typeof post.number === 'function') {
      return app.route.discussion(discussion, post.number());
    }

    return app.forum.attribute('basePath') || '/';
  }

  content() {
    const user = this.attrs.notification.fromUser();

    return app.translator.trans('itqan-discussions.forum.notifications.discussion_replied_text', { user });
  }

  excerpt() {
    const post = this.attrs.notification.subject();

    return truncate((post && post.contentPlain && post.contentPlain()) || '', 200);
  }
}