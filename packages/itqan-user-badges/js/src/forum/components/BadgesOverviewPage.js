import app from 'flarum/forum/app';
import Page from 'flarum/common/components/Page';
import PageStructure from 'flarum/forum/components/PageStructure';
import IndexSidebar from 'flarum/forum/components/IndexSidebar';
import LoadingIndicator from 'flarum/common/components/LoadingIndicator';
import TableView from './BadgeCategoryList/TableView';
import BlockListView from './BadgeCategoryList/BlockListView';

export default class BadgesOverviewPage extends Page {
  oninit(vnode) {
    super.oninit(vnode);

    this.bodyClass = 'App--index';
    this.loading = true;

    app.setTitle(app.translator.trans('v17development-flarum-badges.forum.badge.badges'));

    app.store.find('badge-categories').then(() => {
      this.loading = false;
      m.redraw();
    });
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
    const categories = app.store.all('badge-categories').sort((a, b) => a.order() - b.order());

    return (
      <PageStructure className="IndexPage BadgesOverviewPage" hero={this.hero.bind(this)} sidebar={() => <IndexSidebar />}>
        <h2 className="BadgeOverviewTitle">{app.translator.trans('v17development-flarum-badges.forum.badge.badges')}</h2>

        {this.loading && <LoadingIndicator size="large" />}

        {!this.loading &&
          categories.map((category) => {
            const badges = (category.badges() || []).sort((a, b) => a.order() - b.order());

            return (
              <div className="BadgeCategory">
                <h3>{category.name()}</h3>
                {category.description() && <p>{category.description()}</p>}

                {category.isTable() && <TableView badges={badges} />}

                {!category.isTable() && <BlockListView badges={badges} />}
              </div>
            );
          })}
      </PageStructure>
    );
  }
}
