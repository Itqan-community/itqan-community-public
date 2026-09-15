import app from 'flarum/forum/app';
import { extend, override } from 'flarum/common/extend';
import NotificationList from 'flarum/forum/components/NotificationList';
import NotificationsDropdown from 'flarum/forum/components/NotificationsDropdown';
import DndToggle from './components/DndToggle';

app.initializers.add('itqan-notifications', () => {
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