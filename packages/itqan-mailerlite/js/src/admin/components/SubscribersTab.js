import Component from 'flarum/common/Component';
import Button from 'flarum/common/components/Button';
import LoadingIndicator from 'flarum/common/components/LoadingIndicator';

export default class SubscribersTab extends Component {
  oninit(vnode) {
    super.oninit(vnode);

    this.loading = true;
    this.subscribers = [];
    this.meta = { total: 0, page: 1, per_page: 20, total_pages: 0 };
    this.filters = {
      status: '',
      search: ''
    };
    this.syncingUsers = {};

    this.loadSubscribers();
  }

  view() {
    return (
      <div className="SubscribersTab">
        {this.buildFilters()}

        {this.loading ? (
          <LoadingIndicator />
        ) : this.subscribers.length === 0 ? (
          <div className="AlertBox info">
            {app.translator.trans('itqan-mailerlite.admin.subscribers.no_subscribers')}
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
          <label>{app.translator.trans('itqan-mailerlite.admin.subscribers.filter_status')}</label>
          <select
            value={this.filters.status}
            onchange={(e) => {
              this.filters.status = e.target.value;
              this.meta.page = 1;
              this.loadSubscribers();
            }}
          >
            <option value="">{app.translator.trans('itqan-mailerlite.admin.subscribers.all')}</option>
            <option value="synced">{app.translator.trans('itqan-mailerlite.admin.subscribers.synced')}</option>
            <option value="pending">{app.translator.trans('itqan-mailerlite.admin.subscribers.pending')}</option>
            <option value="failed">{app.translator.trans('itqan-mailerlite.admin.subscribers.failed')}</option>
            <option value="unsubscribed">{app.translator.trans('itqan-mailerlite.admin.subscribers.unsubscribed')}</option>
            <option value="not_synced">{app.translator.trans('itqan-mailerlite.admin.subscribers.not_synced')}</option>
          </select>
        </div>

        <div className="FilterBar-item">
          <label>{app.translator.trans('itqan-mailerlite.admin.subscribers.search')}</label>
          <input
            type="text"
            value={this.filters.search}
            placeholder={app.translator.trans('itqan-mailerlite.admin.subscribers.search_placeholder')}
            oninput={(e) => {
              this.filters.search = e.target.value;
            }}
            onkeydown={(e) => {
              if (e.key === 'Enter') {
                this.meta.page = 1;
                this.loadSubscribers();
              }
            }}
          />
        </div>

        <Button
          className="Button"
          onclick={() => {
            this.meta.page = 1;
            this.loadSubscribers();
          }}
          icon="fas fa-search"
        >
          {app.translator.trans('itqan-mailerlite.admin.subscribers.search_button')}
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
            <th>{app.translator.trans('itqan-mailerlite.admin.table.status')}</th>
            <th>{app.translator.trans('itqan-mailerlite.admin.table.groups')}</th>
            <th>{app.translator.trans('itqan-mailerlite.admin.table.posts')}</th>
            <th>{app.translator.trans('itqan-mailerlite.admin.table.last_synced')}</th>
            <th>{app.translator.trans('itqan-mailerlite.admin.table.actions')}</th>
          </tr>
        </thead>
        <tbody>
          {this.subscribers.map((subscriber) => (
            <tr key={subscriber.id}>
              <td>{subscriber.username}</td>
              <td>{subscriber.email}</td>
              <td>
                <span className={`status-badge ${subscriber.sync_status}`}>
                  {subscriber.sync_status}
                </span>
              </td>
              <td>
                {(subscriber.groups || []).length > 0 ? (
                  subscriber.groups.map((group, index) => (
                    <span key={index} style={{
                      display: 'inline-block',
                      background: '#e0e0e0',
                      padding: '2px 6px',
                      borderRadius: '4px',
                      fontSize: '11px',
                      marginRight: '4px',
                      marginBottom: '2px'
                    }}>
                      {group}
                    </span>
                  ))
                ) : '-'}
              </td>
              <td>{subscriber.comment_count}</td>
              <td>{subscriber.last_synced_at ? this.formatTime(subscriber.last_synced_at) : '-'}</td>
              <td>
                <Button
                  className="Button Button--small"
                  onclick={() => this.syncUser(subscriber.id)}
                  loading={this.syncingUsers[subscriber.id]}
                  disabled={subscriber.sync_status === 'unsubscribed'}
                  icon="fas fa-sync"
                >
                  {app.translator.trans('itqan-mailerlite.admin.subscribers.sync')}
                </Button>
              </td>
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
    this.loadSubscribers();
  }

  async loadSubscribers() {
    this.loading = true;
    m.redraw();

    try {
      const params = {
        page: this.meta.page,
        per_page: this.meta.per_page
      };

      if (this.filters.status) params.status = this.filters.status;
      if (this.filters.search) params.search = this.filters.search;

      const response = await app.request({
        method: 'GET',
        url: `${app.forum.attribute('apiUrl')}/mailerlite/subscribers`,
        params
      });

      this.subscribers = response.data || [];
      this.meta = response.meta || this.meta;
    } catch (error) {
      console.error('Failed to load subscribers:', error);
      this.subscribers = [];
    }

    this.loading = false;
    m.redraw();
  }

  async syncUser(userId) {
    if (this.syncingUsers[userId]) return;

    this.syncingUsers[userId] = true;
    m.redraw();

    try {
      await app.request({
        method: 'POST',
        url: `${app.forum.attribute('apiUrl')}/mailerlite/sync/${userId}`
      });

      // Reload to show updated status
      await this.loadSubscribers();
    } catch (error) {
      console.error('Failed to sync user:', error);
      alert(app.translator.trans('itqan-mailerlite.admin.subscribers.sync_failed'));
    }

    this.syncingUsers[userId] = false;
    m.redraw();
  }

  formatTime(dateString) {
    if (!dateString) return '-';
    const date = new Date(dateString);
    return date.toLocaleString();
  }
}
