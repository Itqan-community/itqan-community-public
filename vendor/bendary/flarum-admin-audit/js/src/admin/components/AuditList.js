import Component from 'flarum/common/Component';
import app from 'flarum/admin/app';
import LoadingIndicator from 'flarum/common/components/LoadingIndicator';
import avatar from 'flarum/common/helpers/avatar';
import username from 'flarum/common/helpers/username';
import dayjs from 'dayjs';
import relativeTime from 'dayjs/plugin/relativeTime';

dayjs.extend(relativeTime);

export default class AuditList extends Component {
  view() {
    const { logs, loading, onLogSelect } = this.attrs;

    if (loading) {
      return (
        <div className="AdminAudit-list AdminAudit-list--loading">
          <LoadingIndicator />
        </div>
      );
    }

    if (!logs || logs.length === 0) {
      return (
        <div className="AdminAudit-list AdminAudit-list--empty">
          <div className="AdminAudit-emptyState">
            <i className="fas fa-inbox"></i>
            <p>{app.translator.trans('bendary-admin-audit.admin.list.empty')}</p>
          </div>
        </div>
      );
    }

    return (
      <div className="AdminAudit-list">
        {logs.map((log) => {
          const actionTranslationKey = `bendary-admin-audit.admin.actions.${log.action()}`;
          let actionText = app.translator.trans(actionTranslationKey);
          if (Array.isArray(actionText)) actionText = actionText[0];
          // Fallback if not translated
          if (typeof actionText === 'string' && actionText.startsWith('bendary-admin-audit')) {
             actionText = log.action().replace(/_/g, ' ');
          }

          let iconClass = 'fas fa-info-circle';
          let iconColorClass = 'AuditIcon--default';

          if (log.action().includes('enable')) { iconClass = 'fas fa-check-circle'; iconColorClass = 'AuditIcon--green'; }
          else if (log.action().includes('disable')) { iconClass = 'fas fa-times-circle'; iconColorClass = 'AuditIcon--red'; }
          else if (log.action().includes('update')) { iconClass = 'fas fa-edit'; iconColorClass = 'AuditIcon--yellow'; }

          const u = log.user();

          return (
            <div className="AdminAudit-listItem" onclick={() => onLogSelect(log)}>
              <div className={`AdminAudit-itemIcon ${iconColorClass}`}>
                <i className={iconClass}></i>
              </div>
              <div className="AdminAudit-itemMain">
                <div className="AdminAudit-itemTitle">
                  <strong>{actionText}</strong>
                  {log.target() ? <span className="AdminAudit-itemTarget">{log.target()}</span> : null}
                </div>
                <div className="AdminAudit-itemMeta">
                  {u ? (
                    <span className="AdminAudit-user">
                      {avatar(u)} {username(u)}
                    </span>
                  ) : (
                    <span className="AdminAudit-user system">System</span>
                  )}
                  <span className="AdminAudit-time">
                    {dayjs(log.createdAt()).fromNow()}
                  </span>
                </div>
              </div>
              <div className="AdminAudit-itemChevron">
                <i className="fas fa-chevron-right"></i>
              </div>
            </div>
          );
        })}
      </div>
    );
  }
}
