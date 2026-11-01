import Notification from 'flarum/forum/components/Notification';
import app from 'flarum/forum/app';

export default class BadgeReceivedNotification extends Notification {
  icon() {
    return 'fas fa-award';
  }

  href() {
    const user = app.session.user;

    return user ? app.route('user.badges', { username: user.slug() }) : app.route('index');
  }

  content() {
    return app.translator.trans('v17development-flarum-badges.forum.notification.title');
  }

  /**
   * The notification payload carries the badge name, so the excerpt does not
   * depend on the badge relationship being included (Flarum 2 only includes the
   * notification's own subject).
   */
  excerpt() {
    const data = this.attrs.notification.content() || {};
    const subject = this.attrs.notification.subject();
    const badge = subject && typeof subject.badge === 'function' ? subject.badge() : null;
    const name = badge ? badge.name() : data.badgeName;

    if (!name) return null;

    if (badge && badge.image()) {
      return <img src={badge.image()} className="UserBadgeImage" alt={name} />;
    }

    return (
      <span className="BadgeNotification-excerpt">
        <i className="icon fas fa-award" /> {name}
      </span>
    );
  }
}
