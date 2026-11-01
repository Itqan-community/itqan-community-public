import app from 'flarum/forum/app';
import { extend } from 'flarum/common/extend';
import UserPage from 'flarum/forum/components/UserPage';
import UserControls from 'flarum/forum/utils/UserControls';
import LinkButton from 'flarum/common/components/LinkButton';
import Button from 'flarum/common/components/Button';
import DiscussionListState from 'flarum/forum/states/DiscussionListState';
import GiveBadgeModal from './components/GiveBadgeModal';
import UserBadgeListState from './states/UserBadgeListState';
import BadgeReceivedNotification from './notification/BadgeReceivedNotification';
import addSidebarNav from './addSidebarNav';
import addBadgeListUserCard from './addBadgeListUserCard';

export { default as extend } from './extend';

app.initializers.add('itqan-user-badges', () => {
  addSidebarNav();

  app.userBadgeListState = new UserBadgeListState({});

  // Badge received notification (alert).
  app.notificationComponents.badgeReceived = BadgeReceivedNotification;

  extend('flarum/forum/components/NotificationGrid', 'notificationTypes', function (items) {
    items.add('badgeReceived', {
      name: 'badgeReceived',
      icon: 'fas fa-user-tag',
      label: app.translator.trans('v17development-flarum-badges.forum.notification.settings'),
    });
  });

  // Profile tab.
  extend(UserPage.prototype, 'navItems', function (items) {
    const user = this.user;

    if (!user) return;

    items.add(
      'badges',
      LinkButton.component(
        {
          href: app.route('user.badges', { username: user.slug() }),
          name: 'badges',
          icon: 'fas fa-user-tag',
        },
        [
          app.translator.trans('v17development-flarum-badges.forum.badge.badges'),
          <span className="Button-badge">{(user.userBadges() || []).length}</span>,
        ]
      ),
      90
    );
  });

  // "Give badge" in the user's moderation controls.
  extend(UserControls, 'moderationControls', function (items, user) {
    if (app.forum.attribute('canGiveBadge')) {
      items.add(
        'giveBadge',
        <Button icon="fas fa-user-tag" onclick={() => app.modal.show(GiveBadgeModal, { user })}>
          {app.translator.trans('v17development-flarum-badges.forum.give_badge')}
        </Button>
      );
    }
  });

  addBadgeListUserCard();

  // The discussion list renders the primary badge next to each author.
  extend(DiscussionListState.prototype, 'requestParams', function (params) {
    if (typeof params.include === 'string') {
      params.include = [params.include];
    }

    params.include = params.include || [];
    params.include.push('user.userBadges', 'user.userBadges.badge');
  });
});
