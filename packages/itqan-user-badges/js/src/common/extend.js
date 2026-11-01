import Extend from 'flarum/common/extenders';
import User from 'flarum/common/models/User';
import Badge from './models/Badge';
import BadgeCategory from './models/BadgeCategory';
import UserBadge from './models/UserBadge';

/**
 * Store types are kebab-case on Flarum 2 (they double as API resource types).
 * The user relationships keep their 1.8 names so the payloads stay identical.
 */
export default [
  new Extend.Store().add('badges', Badge).add('badge-categories', BadgeCategory).add('user-badges', UserBadge),

  new Extend.Model(User).hasMany('userBadges').hasOne('userPrimaryBadge'),
];
