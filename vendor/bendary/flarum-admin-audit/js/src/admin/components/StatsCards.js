import Component from 'flarum/common/Component';
import app from 'flarum/admin/app';

export default class StatsCards extends Component {
  view() {
    const total = this.attrs.total || 0;
    const sensitive = this.attrs.sensitive !== undefined ? this.attrs.sensitive : '--';
    const activeAdmin = this.attrs.activeAdmin || '--';
    const onSensitiveClick = this.attrs.onSensitiveClick;

    return (
      <div className="AdminAudit-stats">
        <div className="AdminAudit-statCard">
          <div className="AdminAudit-statIcon"><i className="fas fa-list-ul"></i></div>
          <div className="AdminAudit-statContent">
            <h3>{app.translator.trans('bendary-admin-audit.admin.stats.total')}</h3>
            <span className="AdminAudit-statValue">{total}</span>
          </div>
        </div>
        
        <div 
          className={'AdminAudit-statCard' + (onSensitiveClick ? ' AdminAudit-statCard--clickable' : '')}
          onclick={onSensitiveClick}
          title={app.translator.trans('bendary-admin-audit.admin.stats.sensitive_tooltip')}
        >
          <div className="AdminAudit-statIcon" style={{color: 'var(--audit-yellow)'}}><i className="fas fa-exclamation-triangle"></i></div>
          <div className="AdminAudit-statContent">
            <h3>
              {app.translator.trans('bendary-admin-audit.admin.stats.sensitive')}
              <span className="AdminAudit-tooltip">
                <i className="fas fa-info-circle"></i>
                <span className="AdminAudit-tooltipText">
                  {app.translator.trans('bendary-admin-audit.admin.stats.sensitive_tooltip')}
                </span>
              </span>
            </h3>
            <span className="AdminAudit-statValue">{sensitive}</span>
          </div>
        </div>

        <div className="AdminAudit-statCard">
          <div className="AdminAudit-statIcon" style={{color: 'var(--audit-green)'}}><i className="fas fa-user-shield"></i></div>
          <div className="AdminAudit-statContent">
            <h3>{app.translator.trans('bendary-admin-audit.admin.stats.active_admin')}</h3>
            <span className="AdminAudit-statValue">{activeAdmin}</span>
          </div>
        </div>
      </div>
    );
  }
}
