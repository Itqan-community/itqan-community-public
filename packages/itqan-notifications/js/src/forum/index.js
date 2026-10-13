import app from 'flarum/forum/app';
import { extend, override } from 'flarum/common/extend';
import NotificationList from 'flarum/forum/components/NotificationList';
import NotificationsDropdown from 'flarum/forum/components/NotificationsDropdown';
import DndToggle from './components/DndToggle';
import DiscussionRepliedNotification from './components/DiscussionRepliedNotification';
import CommentRepliedNotification from './components/CommentRepliedNotification';
import PostApprovedNotification from './components/PostApprovedNotification';
import DiscussionStickiedNotification from './components/DiscussionStickiedNotification';
import DiscussionRetaggedNotification from './components/DiscussionRetaggedNotification';

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

  // Notification components registry
  app.notificationComponents.discussionReplied = DiscussionRepliedNotification;
  app.notificationComponents.commentReplied = CommentRepliedNotification;
  app.notificationComponents.postApproved = PostApprovedNotification;
  app.notificationComponents.discussionStickied = DiscussionStickiedNotification;
  app.notificationComponents.discussionRetagged = DiscussionRetaggedNotification;
});