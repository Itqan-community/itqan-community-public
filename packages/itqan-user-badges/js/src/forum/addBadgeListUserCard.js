import app from 'flarum/forum/app';
import { extend } from 'flarum/common/extend';
import UserCard from 'flarum/forum/components/UserCard';
import Link from 'flarum/common/components/Link';
import Tooltip from 'flarum/common/components/Tooltip';
import BadgeModal from './components/BadgeModal';
import SelectUserCardBadgesModal from './components/SelectUserCardBadgesModal';

/**
 * Badges on the user-card popup.
 *
 * Flarum 2 does not ship `userBadges` with every post author, so the API
 * computes a small `userCardBadges` list on the user resource instead — the
 * card renders that without an extra request per member.
 */
export default function addBadgeListUserCard() {
  extend(UserCard.prototype, 'infoItems', function (items) {
    const user = this.attrs.user;

    if (!app.forum.attribute('showBadgesOnUserCard') || !user) return;

    const list = user.attribute('userCardBadges') || [];
    const total = user.attribute('badgeCount') || list.length;

    if (!list.length) return;

    const badges = list.map((entry) => {
      const badge = app.store.getById('badges', entry.id);

      const node = entry.image ? (
        <img src={entry.image} className="UserBadgeImage" alt={entry.name} />
      ) : (
        <span
          className="UserBadge"
          style={{ backgroundColor: entry.backgroundColor, color: entry.labelColor, borderColor: entry.backgroundColor }}
        >
          <i className={entry.icon || 'fas fa-award'} style={{ color: entry.iconColor }} /> {entry.name}
        </span>
      );

      // The catalogue is not loaded on every page; fetch the badge on click so
      // the details modal still opens (the old build assumed it was in store).
      const open = async (e) => {
        e.preventDefault();
        let model = badge;

        if (!model) {
          try {
            model = await app.store.find('badges', entry.id);
          } catch (err) {
            return;
          }
        }

        app.modal.show(BadgeModal, { badge: model });
      };

      return (
        <Tooltip text={(badge && badge.description()) || entry.name}>
          <span className="UserBadge-clickable" onclick={open}>
            {node}
          </span>
        </Tooltip>
      );
    });

    if (total > list.length) {
      badges.push(
        <Link href={app.route('user.badges', { username: user.slug() })} className="UserBadge more-badges">
          {app.translator.trans('v17development-flarum-badges.forum.badge.others_link', { count: total - list.length })}
        </Link>
      );
    }

    const canEdit =
      (user === app.session.user && app.forum.attribute('editOwnUserCardBadges')) || app.forum.attribute('editUserCardBadges');

    if (canEdit) {
      badges.push(
        <Tooltip text={app.translator.trans('v17development-flarum-badges.forum.badges_in_card.manage_badges')}>
          <a
            href="#"
            className="UserBadge more-badges"
            onclick={(e) => {
              e.preventDefault();
              app.modal.show(SelectUserCardBadgesModal, { user });
            }}
          >
            <i className="fas fa-pencil-alt BadgeIconOnly" />
          </a>
        </Tooltip>
      );
    }

    items.add('badges', badges);
  });
}
