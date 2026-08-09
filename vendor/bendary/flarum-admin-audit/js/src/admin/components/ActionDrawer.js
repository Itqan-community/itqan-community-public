import Component from 'flarum/common/Component';
import app from 'flarum/admin/app';
import dayjs from 'dayjs';

export default class ActionDrawer extends Component {
  view() {
    const { log, isOpen, onClose } = this.attrs;

    if (!log) return null;

    let actionText = log.action();
    const actionTranslationKey = `bendary-admin-audit.admin.actions.${log.action()}`;
    const translated = app.translator.trans(actionTranslationKey);
    if (!Array.isArray(translated) && !translated.startsWith('bendary-admin-audit')) {
      actionText = translated;
    }

    return (
      <div className={`AdminAudit-drawerWrapper ${isOpen ? 'is-open' : ''}`}>
        <div className="AdminAudit-drawerOverlay" onclick={onClose}></div>
        <div className="AdminAudit-drawer">
          <div className="AdminAudit-drawerHeader">
            <h2>{app.translator.trans('bendary-admin-audit.admin.drawer.title')}</h2>
            <button className="Button Button--icon Button--flat" onclick={onClose}>
              <i className="fas fa-times"></i>
            </button>
          </div>
          <div className="AdminAudit-drawerBody">
            <div className="AdminAudit-detailSection">
              <h3>{actionText}</h3>
              {log.target() && <p className="AdminAudit-detailTarget">{log.target()}</p>}
            </div>

            <div className="AdminAudit-metaGrid">
              <div className="AdminAudit-metaItem">
                <span className="AdminAudit-metaLabel">{app.translator.trans('bendary-admin-audit.admin.drawer.user')}</span>
                <span className="AdminAudit-metaVal">
                  {log.user() ? (
                    <div>
                      {log.user().username()} <small className="AdminAudit-textMuted">(ID: {log.user().id()})</small>
                      {log.user().email() && (
                        <div style="font-size: 12px; margin-top: 4px; color: var(--audit-text-muted);">
                          <i className="fas fa-envelope"></i> {log.user().email()}
                        </div>
                      )}
                    </div>
                  ) : 'System'}
                </span>
              </div>
              <div className="AdminAudit-metaItem">
                <span className="AdminAudit-metaLabel">{app.translator.trans('bendary-admin-audit.admin.drawer.ip')}</span>
                <span className="AdminAudit-metaVal">{log.ip() || 'Unknown'}</span>
              </div>
              <div className="AdminAudit-metaItem">
                <span className="AdminAudit-metaLabel">{app.translator.trans('bendary-admin-audit.admin.drawer.time')}</span>
                <span className="AdminAudit-metaVal">{dayjs(log.createdAt()).format('YYYY-MM-DD HH:mm:ss')}</span>
              </div>
            </div>

            {log.oldValue() || log.newValue() ? (
              <div className="AdminAudit-diffSection">
                <h4>{app.translator.trans('bendary-admin-audit.admin.drawer.changes')}</h4>
                
                {log.oldValue() && log.newValue() ? (
                  <div className="AdminAudit-diffGrid">
                    <div className="AdminAudit-diffCol">
                      <div className="AdminAudit-diffLabel AdminAudit-diffLabel--old">
                        <i className="fas fa-minus-circle"></i> {app.translator.trans('bendary-admin-audit.admin.drawer.before')}
                      </div>
                      <div className="AdminAudit-codeBlock AdminAudit-codeBlock--old">
                        <pre><code>{JSON.stringify(log.oldValue(), null, 2)}</code></pre>
                      </div>
                    </div>
                    <div className="AdminAudit-diffCol">
                      <div className="AdminAudit-diffLabel AdminAudit-diffLabel--new">
                        <i className="fas fa-plus-circle"></i> {app.translator.trans('bendary-admin-audit.admin.drawer.after')}
                      </div>
                      <div className="AdminAudit-codeBlock AdminAudit-codeBlock--new">
                        <pre><code>{JSON.stringify(log.newValue(), null, 2)}</code></pre>
                      </div>
                    </div>
                  </div>
                ) : (
                  <div>
                    {log.newValue() && (
                      <div className="AdminAudit-codeBlock AdminAudit-codeBlock--new">
                        <pre><code>{JSON.stringify(log.newValue(), null, 2)}</code></pre>
                      </div>
                    )}
                  </div>
                )}
              </div>
            ) : null}

            {log.meta() && (
              <div className="AdminAudit-diffSection">
                <h4>{app.translator.trans('bendary-admin-audit.admin.drawer.additional_meta')}</h4>
                <div className="AdminAudit-codeBlock">
                  <pre><code>{JSON.stringify(log.meta(), null, 2)}</code></pre>
                </div>
              </div>
            )}
          </div>
        </div>
      </div>
    );
  }
}
