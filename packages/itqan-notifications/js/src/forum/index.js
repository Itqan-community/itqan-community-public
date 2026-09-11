import app from 'flarum/forum/app';
import { extend } from 'flarum/common/extend';
import HeaderSecondary from 'flarum/forum/components/HeaderSecondary';
import DndToggle from './components/DndToggle';

app.initializers.add('itqan-notifications', () => {
  extend(HeaderSecondary.prototype, 'items', function (items) {
    items.add('itqanNotificationsDnd', <DndToggle />, 24);
  });
});