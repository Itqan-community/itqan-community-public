import Component from 'flarum/common/Component';
import Button from 'flarum/common/components/Button';
import LoadingIndicator from 'flarum/common/components/LoadingIndicator';

export default class SyncLogTab extends Component {
  oninit(vnode) {
    super.oninit(vnode);

    this.loading = true;
    this.logs = [];
    this.meta = { total: 0, page: 1, per_page: 20, total_pages: 0 };
    this.filters = {
      status: '',
      action: ''
    };

    this.loadLogs();
  }

  view() {
    return (
      <div className="SyncLogTab">
        {this.buildFilters()}

        {this.loading ? (
          <LoadingIndicator />
        ) : this.logs.length === 0 ? (
          <div className="AlertBox info">
            {app.translator.trans('itqan-mailerlite.admin.logs.no_logs')}
          </div>
        ) : (
          <div>
            {this.buildTable()}
            {this.buildPagination()}
          </div>
        )}
      </div>
    );
  }

  buildFilters() {
    return (
      <div className="FilterBar">
        <div className="FilterBar-item">
          <label>{app.translator.trans('itqan-mailerlite.admin.logs.filter_status')}</label>
          <select
            value={this.filters.status}
            onchange={(e) => {
              this.filters.status = e.target.value;
              this.meta.page = 1;
              this.loadLogs();
            }}
          >
            <option value="">{app.translator.trans('itqan-mailerlite.admin.logs.all')}</option>
            <option value="success">{app.translator.trans('itqan-mailerlite.admin.logs.success')}</option>
            <option value="failed">{app.translator.trans('itqan-mailerlite.admin.logs.failed')}</option>
            <option value="pending">{app.translator.trans('itqan-mailerlite.admin.logs.pending')}</option>
          </select>
        </div>

        <div className="FilterBar-item">
          <label>{app.translator.trans('itqan-mailerlite.admin.logs.filter_action')}</label>
          <select
            value={this.filters.action}
            onchange={(e) => {
              this.filters.action = e.target.value;
              this.meta.page = 1;
              this.loadLogs();
            }}
          >
            <option value="">{app.translator.trans('itqan-mailerlite.admin.logs.all')}</option>
            <option value="sync">{app.translator.trans('itqan-mailerlite.admin.logs.action_sync')}</option>
            <option value="add_to_group">{app.translator.trans('itqan-mailerlite.admin.logs.action_add_to_group')}</option>
            <option value="remove_from_group">{app.translator.trans('itqan-mailerlite.admin.logs.action_remove_from_group')}</option>
            <option value="unsubscribe">{app.translator.trans('itqan-mailerlite.admin.logs.action_unsubscribe')}</option>
          </select>
        </div>

        <Button
          className="Button"
          onclick={() => this.loadLogs()}
          icon="fas fa-sync"
        >
          {app.translator.trans('itqan-mailerlite.admin.logs.refresh')}
        </Button>
      </div>
    );
  }

  buildTable() {
    return (
      <table className="DataTable">
        <thead>
          <tr>
            <th>{app.translator.trans('itqan-mailerlite.admin.table.user')}</th>
            <th>{app.translator.trans('itqan-mailerlite.admin.table.email')}</th>
            <th>{app.translator.trans('itqan-mailerlite.admin.table.action')}</th>
            <th>{app.translator.trans('itqan-mailerlite.admin.table.group')}</th>
            <th>{app.translator.trans('itqan-mailerlite.admin.table.status')}</th>
            <th>{app.translator.trans('itqan-mailerlite.admin.table.error')}</th>
            <th>{app.translator.trans('itqan-mailerlite.admin.table.time')}</th>
          </tr>
        </thead>
        <tbody>
          {this.logs.map((log) => (
            <tr key={log.id}>
              <td>{log.username || 'Unknown'}</td>
              <td>{log.email || '-'}</td>
              <td>{log.action}</td>
              <td>{log.group_name || '-'}</td>
              <td>
                <span className={`status-badge ${log.status}`}>{log.status}</span>
              </td>
              <td title={log.error_message}>
                {log.error_message ? log.error_message.substring(0, 50) + (log.error_message.length > 50 ? '...' : '') : '-'}
              </td>
              <td>{this.formatTime(log.created_at)}</td>
            </tr>
          ))}
        </tbody>
      </table>
    );
  }

  buildPagination() {
    if (this.meta.total_pages <= 1) return null;

    return (
      <div className="Pagination">
        <Button
          className="Button"
          disabled={this.meta.page <= 1}
          onclick={() => this.goToPage(this.meta.page - 1)}
          icon="fas fa-chevron-left"
        />
        <span className="Pagination-info">
          {app.translator.trans('itqan-mailerlite.admin.pagination.page', {
            current: this.meta.page,
            total: this.meta.total_pages
          })}
        </span>
        <Button
          className="Button"
          disabled={this.meta.page >= this.meta.total_pages}
          onclick={() => this.goToPage(this.meta.page + 1)}
          icon="fas fa-chevron-right"
        />
      </div>
    );
  }

  goToPage(page) {
    this.meta.page = page;
    this.loadLogs();
  }

  async loadLogs() {
    this.loading = true;
    m.redraw();

    try {
      const params = {
        page: this.meta.page,
        per_page: this.meta.per_page
      };

      if (this.filters.status) params.status = this.filters.status;
      if (this.filters.action) params.action = this.filters.action;

      const response = await app.request({
        method: 'GET',
        url: `${app.forum.attribute('apiUrl')}/mailerlite/sync-logs`,
        params
      });

      this.logs = response.data || [];
      this.meta = response.meta || this.meta;
    } catch (error) {
      console.error('Failed to load logs:', error);
      this.logs = [];
    }

    this.loading = false;
    m.redraw();
  }

  formatTime(dateString) {
    if (!dateString) return '-';
    const date = new Date(dateString);
    return date.toLocaleString();
  }
}
