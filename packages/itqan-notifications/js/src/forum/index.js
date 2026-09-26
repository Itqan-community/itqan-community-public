import app from 'flarum/forum/app';
import { extend, override } from 'flarum/common/extend';
import NotificationList from 'flarum/forum/components/NotificationList';
import NotificationsDropdown from 'flarum/forum/components/NotificationsDropdown';
import NotificationGrid from 'flarum/forum/components/NotificationGrid';
import DndToggle from './components/DndToggle';
import CommentRepliedNotification from './components/CommentRepliedNotification';

app.initializers.add('itqan-notifications', () => {
  // Register the commentReplied notification component
  app.notificationComponents.commentReplied = CommentRepliedNotification;

  // Add commentReplied to the notification preferences grid
  extend(NotificationGrid.prototype, 'items', function (items) {
    items.add('commentReplied', {
      icon: 'fas fa-reply',
      label: app.translator.trans('itqan-notifications.forum.settings.notify_comment_replied_label'),
    });
  });

  // DnD toggle in notification panel
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
