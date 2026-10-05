import app from 'flarum/forum/app';
import Notification from 'flarum/forum/components/Notification';

export default class DiscussionStickiedNotification extends Notification {
  icon() {
    return 'fas fa-thumbtack';
  }

  href() {
    const discussion = this.attrs.notification.subject();

    if (discussion && typeof discussion.id === 'function') {
      return app.route.discussion(discussion);
    }

    return app.forum.attribute('basePath') || '/';
  }

  content() {
    const discussion = this.attrs.notification.subject();
    const title = discussion && typeof discussion.title === 'function' ? discussion.title() : '';

    return app.translator.trans('itqan-notifications.forum.notifications.discussion_stickied_text', { title });
  }
}
