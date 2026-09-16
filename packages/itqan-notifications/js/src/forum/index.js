import { extend } from 'flarum/common/extend';
import app from 'flarum/forum/app';
import NotificationGrid from 'flarum/forum/components/NotificationGrid';

import DiscussionRepliedNotification from './components/DiscussionRepliedNotification';

app.initializers.add('itqan-notifications', () => {
  app.notificationComponents.discussionReplied = DiscussionRepliedNotification;

  extend(NotificationGrid.prototype, 'notificationTypes', function (items) {
    items.add('discussionReplied', {
      name: 'discussionReplied',
      icon: 'fas fa-reply',
      label: app.translator.trans('itqan-notifications.forum.settings.notify_discussion_replied_label'),
    });
  });
});
