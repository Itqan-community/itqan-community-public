import app from 'flarum/forum/app';
import { extend, override } from 'flarum/common/extend';
import NotificationGrid from 'flarum/forum/components/NotificationGrid';
import NotificationList from 'flarum/forum/components/NotificationList';
import NotificationsDropdown from 'flarum/forum/components/NotificationsDropdown';

import DiscussionRepliedNotification from './components/DiscussionRepliedNotification';
import DndToggle from './components/DndToggle';

app.initializers.add('itqan-notifications', () => {
  app.notificationComponents.discussionReplied = DiscussionRepliedNotification;

  extend(NotificationGrid.prototype, 'notificationTypes', function (items) {
    items.add('discussionReplied', {
      name: 'discussionReplied',
      icon: 'fas fa-reply',
      label: app.translator.trans('itqan-notifications.forum.settings.notify_discussion_replied_label'),
    });
  });

  extend(NotificationList.prototype, 'controlItems', function (items) {
    items.add('itqanNotificationsDnd', <DndToggle />, 100);
  });

  const isDndActive = () => !!(app.session.user && app.session.user.preferences().dndEnabled);

  override(NotificationsDropdown.prototype, 'getUnreadCount', function (original) {
    return isDndActive() ? 0 : original();
  });

  override(NotificationsDropdown.prototype, 'getNewCount', function (original) {
    return isDndActive() ? 0 : original();
  });
});
