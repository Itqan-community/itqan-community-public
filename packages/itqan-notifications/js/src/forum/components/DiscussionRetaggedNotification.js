import app from 'flarum/forum/app';
import Notification from 'flarum/forum/components/Notification';

export default class DiscussionRetaggedNotification extends Notification {
  icon() {
    return 'fas fa-tags';
  }

  href() {
    const discussion = this.attrs.notification.subject();

    if (discussion && typeof discussion.id === 'function') {
      return app.route.discussion(discussion);
    }

    return app.forum.attribute('basePath') || '/';
  }

  content() {
    const notification = this.attrs.notification;
    const discussion = notification.subject();
    const title = discussion && typeof discussion.title === 'function' ? discussion.title() : '';
    const newTag = notification.content() && notification.content().newTag ? notification.content().newTag : '';

    return app.translator.trans('itqan-notifications.forum.notifications.discussion_retagged_text', { title, new_tag: newTag });
  }
}
