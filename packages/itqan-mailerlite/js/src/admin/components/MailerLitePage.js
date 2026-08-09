import ExtensionPage from 'flarum/admin/components/ExtensionPage';
import Button from 'flarum/common/components/Button';
import Switch from 'flarum/common/components/Switch';
import LoadingIndicator from 'flarum/common/components/LoadingIndicator';
import DashboardTab from './DashboardTab';
import GroupsTab from './GroupsTab';
import SyncLogTab from './SyncLogTab';
import SubscribersTab from './SubscribersTab';

export default class MailerLitePage extends ExtensionPage {
  oninit(vnode) {
    super.oninit(vnode);

    this.activeTab = 'dashboard';
    this.connectionStatus = 'checking';
    this.connectionMessage = '';

    this.checkConnection();
  }

  content() {
    return (
      <div className="MailerLitePage">
        <div className="MailerLitePage-header">
          <h2>
            <i className="fas fa-envelope icon"></i>
            {app.translator.trans('itqan-mailerlite.admin.title')}
          </h2>
          {this.buildConnectionStatus()}
        </div>

        {this.buildSettingsForm()}

        <div className="MailerLitePage-tabs">
          {this.buildTabButtons()}
        </div>

        <div className="MailerLitePage-content">
          {this.buildTabContent()}
        </div>
      </div>
    );
  }

  buildConnectionStatus() {
    const statusClass = this.connectionStatus;
    const icons = {
      connected: 'fas fa-check-circle',
      disconnected: 'fas fa-times-circle',
      checking: 'fas fa-spinner fa-spin'
    };

    return (
      <div className={`ConnectionStatus ${statusClass}`}>
        <i className={icons[statusClass]}></i>
        <span>{this.connectionMessage || app.translator.trans(`itqan-mailerlite.admin.connection.${statusClass}`)}</span>
        {statusClass !== 'checking' && (
          <Button
            className="Button Button--text"
            onclick={() => this.checkConnection()}
            icon="fas fa-sync"
          >
            {app.translator.trans('itqan-mailerlite.admin.connection.refresh')}
          </Button>
        )}
      </div>
    );
  }

  buildSettingsForm() {
    return (
      <div className="SettingsForm" style={{ marginBottom: '30px' }}>
        <div className="Form-group">
          <Switch
            state={this.setting('itqan-mailerlite.enabled')() === '1'}
            onchange={(value) => {
              this.setting('itqan-mailerlite.enabled')(value ? '1' : '0');
            }}
          >
            {app.translator.trans('itqan-mailerlite.admin.settings.enabled_label')}
          </Switch>
        </div>

        <div className="Form-group">
          <label>{app.translator.trans('itqan-mailerlite.admin.settings.api_key_label')}</label>
          <input
            type="password"
            className="FormControl"
            bidi={this.setting('itqan-mailerlite.api_key')}
            placeholder="Your MailerLite API key"
          />
          <div className="Form-help">
            {app.translator.trans('itqan-mailerlite.admin.settings.api_key_help')}
          </div>
        </div>

        <details style={{ marginTop: '20px' }}>
          <summary style={{ cursor: 'pointer', fontWeight: 'bold' }}>
            {app.translator.trans('itqan-mailerlite.admin.settings.groups_section')}
          </summary>
          <div style={{ paddingTop: '15px' }}>
            {this.buildGroupSettings()}
          </div>
        </details>

        <details style={{ marginTop: '20px' }}>
          <summary style={{ cursor: 'pointer', fontWeight: 'bold' }}>
            {app.translator.trans('itqan-mailerlite.admin.settings.thresholds_section')}
          </summary>
          <div style={{ paddingTop: '15px' }}>
            {this.buildThresholdSettings()}
          </div>
        </details>

        <details style={{ marginTop: '20px' }}>
          <summary style={{ cursor: 'pointer', fontWeight: 'bold' }}>
            {app.translator.trans('itqan-mailerlite.admin.settings.webhook_section')}
          </summary>
          <div style={{ paddingTop: '15px' }}>
            {this.buildWebhookSettings()}
          </div>
        </details>

        <div className="Form-group" style={{ marginTop: '20px' }}>
          {this.submitButton()}
        </div>
      </div>
    );
  }

  buildGroupSettings() {
    const groups = [
      { key: 'group_new_members', label: 'new_members' },
      { key: 'group_first_posters', label: 'first_posters' },
      { key: 'group_inactive_users', label: 'inactive_users' },
      { key: 'group_power_users', label: 'power_users' }
    ];

    return groups.map(({ key, label }) => (
      <div className="Form-group" key={key}>
        <label>{app.translator.trans(`itqan-mailerlite.admin.settings.${label}_label`)}</label>
        <input
          type="text"
          className="FormControl"
          bidi={this.setting(`itqan-mailerlite.${key}`)}
        />
        <div className="Form-help">
          {app.translator.trans(`itqan-mailerlite.admin.settings.${label}_help`)}
        </div>
      </div>
    ));
  }

  buildThresholdSettings() {
    return [
      <div className="Form-group" key="inactivity">
        <label>{app.translator.trans('itqan-mailerlite.admin.settings.inactivity_days_label')}</label>
        <input
          type="number"
          className="FormControl"
          min="1"
          bidi={this.setting('itqan-mailerlite.inactivity_days')}
        />
        <div className="Form-help">
          {app.translator.trans('itqan-mailerlite.admin.settings.inactivity_days_help')}
        </div>
      </div>,
      <div className="Form-group" key="power">
        <label>{app.translator.trans('itqan-mailerlite.admin.settings.power_user_posts_label')}</label>
        <input
          type="number"
          className="FormControl"
          min="1"
          bidi={this.setting('itqan-mailerlite.power_user_posts')}
        />
        <div className="Form-help">
          {app.translator.trans('itqan-mailerlite.admin.settings.power_user_posts_help')}
        </div>
      </div>
    ];
  }

  buildWebhookSettings() {
    const webhookUrl = `${app.forum.attribute('baseUrl')}/api/mailerlite/webhook`;

    return [
      <div className="Form-group" key="url">
        <label>{app.translator.trans('itqan-mailerlite.admin.settings.webhook_url_label')}</label>
        <input
          type="text"
          className="FormControl"
          value={webhookUrl}
          readonly
          onclick={(e) => {
            e.target.select();
            navigator.clipboard.writeText(webhookUrl);
          }}
        />
        <div className="Form-help">
          {app.translator.trans('itqan-mailerlite.admin.settings.webhook_url_help')}
        </div>
      </div>,
      <div className="Form-group" key="secret">
        <label>{app.translator.trans('itqan-mailerlite.admin.settings.webhook_secret_label')}</label>
        <input
          type="password"
          className="FormControl"
          bidi={this.setting('itqan-mailerlite.webhook_secret')}
        />
        <div className="Form-help">
          {app.translator.trans('itqan-mailerlite.admin.settings.webhook_secret_help')}
        </div>
      </div>
    ];
  }

  buildTabButtons() {
    const tabs = [
      { id: 'dashboard', icon: 'fas fa-tachometer-alt', label: 'dashboard' },
      { id: 'subscribers', icon: 'fas fa-users', label: 'subscribers' },
      { id: 'groups', icon: 'fas fa-layer-group', label: 'groups' },
      { id: 'logs', icon: 'fas fa-history', label: 'logs' }
    ];

    return tabs.map(({ id, icon, label }) => (
      <Button
        key={id}
        className={`Button ${this.activeTab === id ? 'active' : ''}`}
        onclick={() => {
          this.activeTab = id;
          m.redraw();
        }}
        icon={icon}
      >
        {app.translator.trans(`itqan-mailerlite.admin.tabs.${label}`)}
      </Button>
    ));
  }

  buildTabContent() {
    switch (this.activeTab) {
      case 'dashboard':
        return <DashboardTab connectionStatus={this.connectionStatus} />;
      case 'subscribers':
        return <SubscribersTab />;
      case 'groups':
        return <GroupsTab />;
      case 'logs':
        return <SyncLogTab />;
      default:
        return null;
    }
  }

  async checkConnection() {
    this.connectionStatus = 'checking';
    this.connectionMessage = '';
    m.redraw();

    try {
      const response = await app.request({
        method: 'GET',
        url: `${app.forum.attribute('apiUrl')}/mailerlite/test-connection`
      });

      if (response.success) {
        this.connectionStatus = 'connected';
        this.connectionMessage = response.message;
      } else {
        this.connectionStatus = 'disconnected';
        this.connectionMessage = response.message || response.error;
      }
    } catch (error) {
      this.connectionStatus = 'disconnected';
      this.connectionMessage = error.message || 'Connection failed';
    }

    m.redraw();
  }
}
