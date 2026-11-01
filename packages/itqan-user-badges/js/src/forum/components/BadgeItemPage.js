import Page from 'flarum/common/components/Page';
import PageStructure from 'flarum/forum/components/PageStructure';
import IndexSidebar from 'flarum/forum/components/IndexSidebar';
import LoadingIndicator from 'flarum/common/components/LoadingIndicator';
import LinkButton from 'flarum/common/components/LinkButton';
import BadgeUserList from './BadgeUserList';
import app from 'flarum/forum/app';

export default class BadgeItemPage extends Page {
  oninit(vnode) {
    super.oninit(vnode);

    this.bodyClass = 'App--index';

    const found = app.store.getById('badges', m.route.param('id'));

    this.loading = !found;

    if (!found) {
      // Load badge categories
      app.store.find(`badges/${m.route.param('id')}`).then((badge) => {
        this.loading = false;

        app.history.push('badgeItemPage', badge.name());

        this.setTitle(badge);

        m.redraw();
      });
    } else {
      app.history.push('badgeItemPage', found.name());

      this.setTitle(found);
    }
  }

  setTitle(badge) {
    app.setTitle(`${badge.name()} - ${app.translator.trans('v17development-flarum-badges.forum.badge.badges')}`);
  }

  hero() {
    return (
      <header className="Hero BadgesHero">
        <div className="container">
          <div className="containerNarrow">
            <h1 className="Hero-title">
              <i aria-hidden="true" className="icon fas fa-user-tag" /> {app.translator.trans('v17development-flarum-badges.forum.badge.badges')}
            </h1>
          </div>
        </div>
      </header>
    );
  }

  view() {
    const badge = app.store.getById('badges', m.route.param('id'));

    return (
      <PageStructure className="IndexPage BadgeItemPage" hero={this.hero.bind(this)} sidebar={() => <IndexSidebar />}>
        <LinkButton href={app.route('badges')} icon="fas fa-chevron-left" className="Button BadgesOverviewButton">
          {app.translator.trans('v17development-flarum-badges.forum.badge.badges')}
        </LinkButton>

        {this.loading && <LoadingIndicator size="large" />}

        {!this.loading && badge && (
          <div className="BadgeUserListInfo">
            {/* Production's badges are images, not FontAwesome icons, so the
                artwork belongs at the top of its own page too. */}
            {badge.image() ? (
              <img src={badge.image()} className="UserBadgeImage BadgeUserListInfo-image" alt={badge.name()} />
            ) : (
              <i className={badge.icon()} />
            )}

            <div className="BadgeUserListInfo-meta">
              <h3>{badge.name()}</h3>
              <p>{badge.description()}</p>
            </div>
          </div>
        )}

        {badge && (
          <h3>
            {app.translator.trans('v17development-flarum-badges.forum.badge.earned_by_count', {
              count: badge.earnedAmount(),
            })}
          </h3>
        )}

        {!this.loading && badge && app.forum.attribute('canViewDetailedBadgeUsers') && (
          <BadgeUserList state={app.userBadgeListState} badgeId={badge.id()} />
        )}
      </PageStructure>
    );
  }
}
