import Component from 'flarum/common/Component';
import Button from 'flarum/common/components/Button';
import LoadingIndicator from 'flarum/common/components/LoadingIndicator';

export default class GroupsTab extends Component {
  oninit(vnode) {
    super.oninit(vnode);

    this.loading = true;
    this.groups = [];
    this.configuredGroups = {};

    this.loadGroups();
  }

  view() {
    if (this.loading) {
      return <LoadingIndicator />;
    }

    return (
      <div className="GroupsTab">
        <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '20px' }}>
          <h3>{app.translator.trans('itqan-mailerlite.admin.groups.title')}</h3>
          <Button
            className="Button"
            onclick={() => this.loadGroups(true)}
            icon="fas fa-sync"
          >
            {app.translator.trans('itqan-mailerlite.admin.groups.refresh')}
          </Button>
        </div>

        {this.groups.length === 0 ? (
          <div className="AlertBox info">
            {app.translator.trans('itqan-mailerlite.admin.groups.no_groups')}
          </div>
        ) : (
          <div className="GroupsList">
            {this.groups.map((group) => this.buildGroupCard(group))}
          </div>
        )}
      </div>
    );
  }

  buildGroupCard(group) {
    return (
      <div className={`GroupCard ${group.is_configured ? 'configured' : ''}`} key={group.id}>
        <div className="GroupCard-info">
          <span className="GroupCard-name">{group.name}</span>
          {group.is_configured && (
            <span className="GroupCard-config">
              {this.getConfigLabel(group.config_key)}
            </span>
          )}
        </div>
        <div className="GroupCard-stats">
          <span>
            <i className="fas fa-users"></i>
            {group.active_count} {app.translator.trans('itqan-mailerlite.admin.groups.subscribers')}
          </span>
          <span>
            <i className="fas fa-envelope"></i>
            {group.sent_count} {app.translator.trans('itqan-mailerlite.admin.groups.sent')}
          </span>
          <span>
            <i className="fas fa-envelope-open"></i>
            {group.opens_count} {app.translator.trans('itqan-mailerlite.admin.groups.opens')}
          </span>
        </div>
      </div>
    );
  }

  getConfigLabel(configKey) {
    const labels = {
      group_new_members: 'New Members',
      group_first_posters: 'First Posters',
      group_inactive_users: 'Inactive Users',
      group_power_users: 'Power Users'
    };
    return labels[configKey] || configKey;
  }

  async loadGroups(refresh = false) {
    this.loading = true;
    m.redraw();

    try {
      const response = await app.request({
        method: 'GET',
        url: `${app.forum.attribute('apiUrl')}/mailerlite/groups`,
        params: { refresh: refresh ? '1' : '0' }
      });

      if (response.success) {
        this.groups = response.data || [];
        this.configuredGroups = response.configured_groups || {};
      } else {
        console.error('Failed to load groups:', response.error);
        this.groups = [];
      }
    } catch (error) {
      console.error('Failed to load groups:', error);
      this.groups = [];
    }

    this.loading = false;
    m.redraw();
  }
}
