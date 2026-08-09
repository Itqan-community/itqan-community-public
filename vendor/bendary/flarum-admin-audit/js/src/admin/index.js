import app from 'flarum/admin/app';
import AuditLog from './models/AuditLog';
import AuditPage from './components/AuditPage';

app.initializers.add('bendary-admin-audit', () => {
  app.store.models['admin_audit_logs'] = AuditLog;

  app.extensionData
    .for('bendary-admin-audit')
    .registerPage(AuditPage);
});
