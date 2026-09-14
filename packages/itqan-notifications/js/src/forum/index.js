import app from 'flarum/forum/app';
import { extend } from 'flarum/common/extend';
import NotificationList from 'flarum/forum/components/NotificationList';
import DndToggle from './components/DndToggle';

app.initializers.add('itqan-notifications', () => {
  extend(NotificationList.prototype, 'controlItems', function (items) {
    items.add('itqanNotificationsDnd', <DndToggle />, 100);
  });
});