import { extend } from 'flarum/common/extend';
import UserPage from 'flarum/forum/components/UserPage';
import LinkButton from 'flarum/common/components/LinkButton';
import WarningPage from './components/WarningPage';

export default function () {
  app.routes['user.warnings'] = {
    path: '/u/:username/warnings',
    component: WarningPage,
  };

  extend(UserPage.prototype, 'navItems', function (items) {
    if (
      app.session.user &&
      (app.session.user.canViewWarnings() || (this.user.id() === app.session.user.id() && this.user.visibleWarningCount() > 0))
    ) {
      items.add(
        'warnings',
        LinkButton.component(
          {
            href: app.route('user.warnings', {
              username: this.user.slug(),
            }),
            icon: 'fas fa-exclamation-circle',
          },
          [
            app.translator.trans('fof-moderator-warnings.forum.user.warnings'),
            this.user.visibleWarningCount() > 0 ? <span className="Button-badge">{this.user.visibleWarningCount()}</span> : '',
          ]
        ),
        10
      );
    }
  });
}
