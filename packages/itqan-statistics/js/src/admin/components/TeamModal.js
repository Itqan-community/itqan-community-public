import app from 'flarum/admin/app';
import Modal from 'flarum/common/components/Modal';
import Button from 'flarum/common/components/Button';
import Switch from 'flarum/common/components/Switch';

export default class TeamModal extends Modal {
  oninit(vnode) {
    super.oninit(vnode);

    const team = this.attrs.team || null;
    this.isEdit = !!team;
    this.name = team ? team.name : '';
    this.description = team ? team.description || '' : '';
    this.color = team ? team.color || '#0d9488' : '#0d9488';
    this.isVisible = team ? !!team.is_visible : true;
    this.saving = false;
  }

  className() {
    return 'ItqanStats-TeamModal Modal--small';
  }

  title() {
    return app.translator.trans(
      this.isEdit ? 'itqan-statistics.admin.teams.edit_team' : 'itqan-statistics.admin.teams.create_team'
    );
  }

  content() {
    return (
      <div className="Modal-body">
        <div className="Form-group">
          <label>{app.translator.trans('itqan-statistics.admin.teams.team_name')}</label>
          <input
            type="text"
            className="FormControl"
            value={this.name}
            oninput={(e) => {
              this.name = e.target.value;
            }}
            required
          />
        </div>

        <div className="Form-group">
          <label>{app.translator.trans('itqan-statistics.admin.teams.team_desc')}</label>
          <textarea
            className="FormControl"
            rows="2"
            value={this.description}
            oninput={(e) => {
              this.description = e.target.value;
            }}
          />
        </div>

        <div className="Form-group">
          <label>{app.translator.trans('itqan-statistics.admin.teams.team_color')}</label>
          <div style={{ display: 'flex', gap: '10px', alignItems: 'center' }}>
            <input
              type="color"
              value={this.color}
              oninput={(e) => {
                this.color = e.target.value;
              }}
              style={{ width: '44px', height: '36px', padding: '2px', border: '1px solid #cbd5e1', borderRadius: '6px' }}
            />
            <input
              type="text"
              className="FormControl"
              value={this.color}
              oninput={(e) => {
                this.color = e.target.value;
              }}
            />
          </div>
        </div>

        <div className="Form-group">
          <Switch
            state={this.isVisible}
            onchange={(val) => {
              this.isVisible = val;
            }}
          >
            {app.translator.trans('itqan-statistics.admin.teams.team_visible')}
          </Switch>
        </div>

        <div className="Form-group" style={{ display: 'flex', gap: '10px', justifyContent: 'flex-end', marginTop: '20px' }}>
          <Button className="Button" onclick={() => this.hide()}>
            {app.translator.trans('itqan-statistics.admin.teams.cancel')}
          </Button>
          <Button className="Button Button--primary" type="submit" loading={this.saving}>
            {app.translator.trans('itqan-statistics.admin.teams.save_team')}
          </Button>
        </div>
      </div>
    );
  }

  onsubmit(e) {
    e.preventDefault();
    if (!this.name.trim()) return;

    this.saving = true;
    const apiUrl = app.forum.attribute('apiUrl');
    const method = this.isEdit ? 'PATCH' : 'POST';
    const url = this.isEdit
      ? `${apiUrl}/itqan-statistics/teams/${this.attrs.team.id}`
      : `${apiUrl}/itqan-statistics/teams`;

    app
      .request({
        method,
        url,
        body: {
          name: this.name.trim(),
          description: this.description.trim(),
          color: this.color,
          is_visible: this.isVisible,
        },
      })
      .then(() => {
        this.saving = false;
        if (this.attrs.onSaved) {
          this.attrs.onSaved();
        }
        this.hide();
      })
      .catch(() => {
        this.saving = false;
        m.redraw();
      });
  }
}
