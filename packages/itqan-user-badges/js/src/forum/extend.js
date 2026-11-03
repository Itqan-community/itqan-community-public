import Extend from 'flarum/common/extenders';
import UserPageResolver from 'flarum/forum/resolvers/UserPageResolver';
import commonExtend from '../common/extend';
import BadgesOverviewPage from './components/BadgesOverviewPage';
import BadgeItemPage from './components/BadgeItemPage';
import BadgesProfilePage from './components/BadgesProfilePage';

export default [
  ...commonExtend,
  new Extend.Routes()
    .add('badges', '/badges', BadgesOverviewPage)
    .add('badges.item', '/badges/:id', BadgeItemPage)
    .add('user.badges', '/u/:username/badges', BadgesProfilePage, UserPageResolver),
];
