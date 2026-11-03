import app from 'flarum/admin/app';
import SettingsPage from './components/SettingsPage';

export { default as extend } from './extend';

app.initializers.add('itqan-user-badges', () => {
  app.registry
    .for('itqan-user-badges')
    .registerPage(SettingsPage)
    .registerPermission(
      {
        icon: 'fas fa-users',
        label: app.translator.trans('v17development-flarum-badges.admin.permissions.badge_detail_users'),
        permission: 'badges.canViewDetailedUsers',
        allowGuest: true,
      },
      'view',
      90
    )
    .registerPermission(
      {
        icon: 'fas fa-id-card',
        label: app.translator.trans('v17development-flarum-badges.admin.permissions.edit_own_card_badges'),
        permission: 'badges.editOwnUserCardBadges',
      },
      'start',
      90
    )
    .registerPermission(
      {
        icon: 'fas fa-address-card',
        label: app.translator.trans('v17development-flarum-badges.admin.permissions.edit_card_badges'),
        permission: 'badges.editUserCardBadges',
      },
      'moderate',
      90
    )
    .registerPermission(
      {
        icon: 'fas fa-user-tag',
        label: app.translator.trans('v17development-flarum-badges.admin.permissions.give_badge'),
        permission: 'badges.giveBadge',
      },
      'moderate',
      90
    )
    .registerPermission(
      {
        icon: 'fas fa-user-times',
        label: app.translator.trans('v17development-flarum-badges.admin.permissions.take_badge'),
        permission: 'badges.removeBadge',
      },
      'moderate',
      90
    );
});
