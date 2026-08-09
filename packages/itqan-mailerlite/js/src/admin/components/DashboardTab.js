import Component from 'flarum/common/Component';
import Button from 'flarum/common/components/Button';
import LoadingIndicator from 'flarum/common/components/LoadingIndicator';

export default class DashboardTab extends Component {
  oninit(vnode) {
    super.oninit(vnode);

    this.loading = true;
    this.stats = {
      totalSubscribers: 0,
      syncedSubscribers: 0,
      failedSubscribers: 0,
      pendingSubscribers: 0,
      recentLogs: []
    };
    this.syncing = false;

    this.loadStats();
  }

  view() {
    if (this.loading) {
      return <LoadingIndicator />;
    }

    return (
      <div className="DashboardTab">
        {this.buildQuickActions()}
        {this.buildStatsGrid()}
        {this.buildRecentActivity()}
      </div>
    );
  }

  buildQuickActions() {
    return (
      <div style={{ marginBottom: '20px' }}>
        <h3>{app.translator.trans('itqan-mailerlite.admin.dashboard.quick_actions')}</h3>
        <div style={{ display: 'flex', gap: '10px' }}>
          <Button
            className="Button Button--primary"
            onclick={() => this.runFullSync()}
            loading={this.syncing}
            icon="fas fa-sync"
          >
            {app.translator.trans('itqan-mailerlite.admin.dashboard.sync_all_users')}
          </Button>
          <Button
            className="Button"
            onclick={() => this.loadStats()}
            icon="fas fa-refresh"
          >
            {app.translator.trans('itqan-mailerlite.admin.dashboard.refresh_stats')}
          </Button>
        </div>
      </div>
    );
  }

  buildStatsGrid() {
    const stats = [
      { value: this.stats.totalSubscribers, label: 'total_users', color: '#1976d2' },
      { value: this.stats.syncedSubscribers, label: 'synced', color: '#09c269' },
      { value: this.stats.pendingSubscribers, label: 'pending', color: '#f57c00' },
      { value: this.stats.failedSubscribers, label: 'failed', color: '#d32f2f' }
    ];

    return (
      <div className="StatsGrid">
        {stats.map(({ value, label, color }) => (
          <div className="StatCard" key={label}>
            <div className="StatCard-value" style={{ color }}>
              {value}
            </div>
            <div className="StatCard-label">
              {app.translator.trans(`itqan-mailerlite.admin.dashboard.stats.${label}`)}
            </div>
          </div>
        ))}
      </div>
    );
  }

  buildRecentActivity() {
    return (
      <div>
        <h3>{app.translator.trans('itqan-mailerlite.admin.dashboard.recent_activity')}</h3>
        {this.stats.recentLogs.length === 0 ? (
          <div className="AlertBox info">
            {app.translator.trans('itqan-mailerlite.admin.dashboard.no_recent_activity')}
          </div>
        ) : (
          <table className="DataTable">
            <thead>
              <tr>
                <th>{app.translator.trans('itqan-mailerlite.admin.table.user')}</th>
                <th>{app.translator.trans('itqan-mailerlite.admin.table.action')}</th>
                <th>{app.translator.trans('itqan-mailerlite.admin.table.status')}</th>
                <th>{app.translator.trans('itqan-mailerlite.admin.table.time')}</th>
              </tr>
            </thead>
            <tbody>
              {this.stats.recentLogs.map((log) => (
                <tr key={log.id}>
                  <td>{log.username || 'Unknown'}</td>
                  <td>{log.action}</td>
                  <td>
                    <span className={`status-badge ${log.status}`}>{log.status}</span>
                  </td>
                  <td>{this.formatTime(log.created_at)}</td>
                </tr>
              ))}
            </tbody>
          </table>
        )}
      </div>
    );
  }

  async loadStats() {
    this.loading = true;
    m.redraw();

    try {
      // Load subscribers stats
      const subscribersResponse = await app.request({
        method: 'GET',
        url: `${app.forum.attribute('apiUrl')}/mailerlite/subscribers`,
        params: { per_page: 1 }
      });

      // Load recent logs
      const logsResponse = await app.request({
        method: 'GET',
        url: `${app.forum.attribute('apiUrl')}/mailerlite/sync-logs`,
        params: { per_page: 5 }
      });

      // Calculate stats from different status queries
      const statuses = ['synced', 'pending', 'failed'];
      const statusCounts = {};

      for (const status of statuses) {
        const response = await app.request({
          method: 'GET',
          url: `${app.forum.attribute('apiUrl')}/mailerlite/subscribers`,
          params: { status, per_page: 1 }
        });
        statusCounts[status] = response.meta?.total || 0;
      }

      this.stats = {
        totalSubscribers: subscribersResponse.meta?.total || 0,
        syncedSubscribers: statusCounts.synced,
        pendingSubscribers: statusCounts.pending,
        failedSubscribers: statusCounts.failed,
        recentLogs: logsResponse.data || []
      };
    } catch (error) {
      console.error('Failed to load stats:', error);
    }

    this.loading = false;
    m.redraw();
  }

  async runFullSync() {
    if (this.syncing) return;

    this.syncing = true;
    m.redraw();

    try {
      const response = await app.request({
        method: 'POST',
        url: `${app.forum.attribute('apiUrl')}/mailerlite/sync-all`,
      });

      alert(`✓ ${response.message || 'Sync started'}`);
      await this.loadStats();
    } catch (error) {
      console.error('Sync failed:', error);
      alert('Sync failed: ' + (error.message || 'Unknown error'));
    }

    this.syncing = false;
    m.redraw();
  }

  formatTime(dateString) {
    if (!dateString) return '-';
    const date = new Date(dateString);
    return date.toLocaleString();
  }
}
