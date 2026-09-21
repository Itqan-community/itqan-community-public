import app from 'flarum/forum/app';
import Notification from 'flarum/forum/components/Notification';
import { truncate } from 'flarum/common/utils/string';

export default class DiscussionRepliedNotification extends Notification {
  icon() {
    return 'fas fa-reply';
  }

  href() {
    const post = this.attrs.notification.subject();

    return post ? app.route.post(post) : app.forum.attribute('basePath') || '/';
  }

  content() {
    const user = this.attrs.notification.fromUser();

    return app.translator.trans('itqan-notifications.forum.notifications.discussion_replied_text', { user });
  }

  excerpt() {
    const post = this.attrs.notification.subject();

    return truncate((post && post.contentPlain()) || '', 200);
  }
}
