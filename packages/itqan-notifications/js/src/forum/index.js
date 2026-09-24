import app from 'flarum/forum/app';
import Model from 'flarum/common/Model';
import { extend, override } from 'flarum/common/extend';
import NotificationList from 'flarum/forum/components/NotificationList';
import NotificationsDropdown from 'flarum/forum/components/NotificationsDropdown';
import DndToggle from './components/DndToggle';
import extendSubscriptionModal from './extendSubscriptionModal';

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

  if (app.store.models.tags) {
    app.store.models.tags.prototype.itqanNotificationChannel = Model.attribute('itqanNotificationChannel');
  }

  if ('fof-follow-tags' in flarum.extensions) {
    extendSubscriptionModal();
  }
});
