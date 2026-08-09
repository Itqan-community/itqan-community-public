import ExtensionPage from 'flarum/admin/components/ExtensionPage';
import StatsCards from './StatsCards';
import AuditFilters from './AuditFilters';
import AuditList from './AuditList';
import ActionDrawer from './ActionDrawer';
import app from 'flarum/admin/app';

export default class AuditPage extends ExtensionPage {
  oninit(vnode) {
    super.oninit(vnode);

    this.logs = [];
    this.meta = { total: 0, sensitiveCount: '--', activeAdmin: '--' };
    this.loading = true;

    this.filters = {
      q: '',
      category: '',
      user: '',
      dateRange: '', // comma separated bounds if needed
    };
    
    this.page = 1;
    this.limit = 20;
    
    this.selectedLog = null;

    this.loadLogs();
  }

  loadLogs() {
    this.loading = true;
    m.redraw();

    const params = {
      page: { offset: (this.page - 1) * this.limit, limit: this.limit },
      filter: this.filters,
      sort: '-createdAt'
    };

    app.store.find('admin_audit_logs', params).then((results) => {
      this.logs = results;
      this.meta = results.payload.meta;
      this.loading = false;
      m.redraw();
    });
  }

  content() {
    return (
      <div className="AdminAudit-container">
        <div className="AdminAudit-header">
          <h2>{app.translator.trans('bendary-admin-audit.admin.page.title')}</h2>
        </div>

        <div className="AdminAudit-body">
          <StatsCards 
            total={this.meta.total} 
            sensitive={this.meta.sensitiveCount}
            activeAdmin={this.meta.activeAdmin}
            onSensitiveClick={() => {
              this.filters.category = this.filters.category === 'sensitive' ? '' : 'sensitive';
              this.page = 1;
              this.loadLogs();
            }}
          />
          
          <AuditFilters 
            filters={this.filters} 
            onFilterChange={(newFilters) => {
              this.filters = { ...this.filters, ...newFilters };
              this.page = 1;
              this.loadLogs();
            }} 
          />

          <AuditList 
            logs={this.logs} 
            loading={this.loading} 
            onLogSelect={(log) => {
              this.selectedLog = log;
              m.redraw();
            }}
          />

          <div className="AdminAudit-pagination">
            <button 
              className="Button Button--primary" 
              disabled={this.page <= 1} 
              onclick={() => {
                this.page--;
                this.loadLogs();
              }}>
              <i className="fas fa-chevron-left"></i>
            </button>
            <span className="AdminAudit-pagination-info">
              {app.translator.trans('bendary-admin-audit.admin.page.pagination', { page: this.page })}
            </span>
            <button 
              className="Button Button--primary" 
              disabled={this.logs.length < this.limit} 
              onclick={() => {
                this.page++;
                this.loadLogs();
              }}>
              <i className="fas fa-chevron-right"></i>
            </button>
          </div>
        </div>

        <ActionDrawer 
          log={this.selectedLog} 
          isOpen={!!this.selectedLog} 
          onClose={() => {
            this.selectedLog = null;
            m.redraw();
          }} 
        />
      </div>
    );
  }
}
