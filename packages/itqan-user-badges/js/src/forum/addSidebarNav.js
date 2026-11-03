import { extend } from 'flarum/common/extend';
import IndexPage from 'flarum/forum/components/IndexPage';
import LinkButton from 'flarum/common/components/LinkButton';
import app from 'flarum/forum/app';

export default function addSidebarNav() {
  extend(IndexPage.prototype, 'navItems', function (items) {
    items.add(
      'badges',
      <LinkButton icon="fas fa-id-badge" href={app.route('badges')}>
        {app.translator.trans('v17development-flarum-badges.forum.badge.badges')}
      </LinkButton>,
      15
    );

    return items;
  });
}
