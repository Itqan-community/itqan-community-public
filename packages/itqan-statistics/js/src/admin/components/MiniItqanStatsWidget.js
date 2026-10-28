import app from 'flarum/admin/app';
import DashboardWidget from 'flarum/admin/components/DashboardWidget';
import Button from 'flarum/common/components/Button';
import LoadingIndicator from 'flarum/common/components/LoadingIndicator';
import icon from '../../common/icon';
import { formatNumber } from '../utils/charts';

export default class MiniItqanStatsWidget extends DashboardWidget {
  oninit(vnode) {
    super.oninit(vnode);
    this.loading = true;
    this.data = null;
  }

  oncreate(vnode) {
    super.oncreate(vnode);
    app
      .request({
        method: 'GET',
        url: `${app.forum.attribute('apiUrl')}/itqan-statistics/dashboard`,
        params: { period: 'mtd' },
      })
      .then((res) => {
        this.data = res;
        this.loading = false;
        m.redraw();
      })
      .catch(() => {
        this.loading = false;
        m.redraw();
      });
  }

  className() {
    return 'ItqanStats-MiniWidget';
  }

  content() {
    if (this.loading) {
      return (
        <div className="ItqanStats-MiniWidget-body">
          <LoadingIndicator />
        </div>
      );
    }

    if (!this.data) return null;

    const { lifetime, period_summary, engagement, window: win } = this.data;

    return (
      <div className="ItqanStats-MiniWidget-body">
        <div className="ItqanStats-MiniWidget-header">
          <div>
            <strong>{icon('fas fa-chart-line')} {app.translator.trans('itqan-statistics.admin.title')}</strong>
            <span className="ItqanStats-MiniWidget-range">
              {win.from} → {win.to} ({app.translator.trans('itqan-statistics.admin.period.mtd')})
            </span>
          </div>
          <Button
            className="Button Button--small"
            icon="fas fa-arrow-right"
            onclick={() => m.route.set(app.route('extension', { id: 'itqan-statistics' }))}
          >
            {app.translator.trans('itqan-statistics.admin.nav.dashboard')}
          </Button>
        </div>

        <div className="ItqanStats-MiniWidget-grid">
          <div className="ItqanStats-MiniWidget-item">
            <span className="ItqanStats-MiniWidget-label">{app.translator.trans('itqan-statistics.admin.trend.users')}</span>
            <span className="ItqanStats-MiniWidget-val">{formatNumber(period_summary.users.current)}</span>
            <span className="ItqanStats-MiniWidget-sub">{formatNumber(lifetime.users)} total</span>
          </div>
          <div className="ItqanStats-MiniWidget-item">
            <span className="ItqanStats-MiniWidget-label">{app.translator.trans('itqan-statistics.admin.trend.discussions')}</span>
            <span className="ItqanStats-MiniWidget-val">{formatNumber(period_summary.discussions.current)}</span>
            <span className="ItqanStats-MiniWidget-sub">{formatNumber(lifetime.discussions)} total</span>
          </div>
          <div className="ItqanStats-MiniWidget-item">
            <span className="ItqanStats-MiniWidget-label">{app.translator.trans('itqan-statistics.admin.trend.posts')}</span>
            <span className="ItqanStats-MiniWidget-val">{formatNumber(period_summary.posts.current)}</span>
            <span className="ItqanStats-MiniWidget-sub">{formatNumber(lifetime.posts)} total</span>
          </div>
          <div className="ItqanStats-MiniWidget-item">
            <span className="ItqanStats-MiniWidget-label">{app.translator.trans('itqan-statistics.admin.engagement.active_1')}</span>
            <span className="ItqanStats-MiniWidget-val">{formatNumber(engagement.active_1)}</span>
            <span className="ItqanStats-MiniWidget-sub">{formatNumber(engagement.with_posts)} creators</span>
          </div>
        </div>
      </div>
    );
  }
}
