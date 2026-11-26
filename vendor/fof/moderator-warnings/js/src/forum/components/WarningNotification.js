import Notification from 'flarum/forum/components/Notification';
import username from 'flarum/common/helpers/username';

export default class WarningNotification extends Notification {
  icon() {
    return 'fas fa-exclamation-circle';
  }

  href() {
    return app.route('user.warnings', {
      username: app.session.user.username(),
    });
  }

  content() {
    const warning = this.attrs.notification.subject();

    if (warning.strikes()) {
      return app.translator.trans('fof-moderator-warnings.forum.notifications.warning_text', {
        mod_username: username(this.attrs.notification.fromUser()),
        strikes: warning.strikes() || 0,
      });
    } else {
      return app.translator.trans('fof-moderator-warnings.forum.notifications.warning_no_strikes_text', {
        mod_username: username(this.attrs.notification.fromUser()),
      });
    }
  }
}
