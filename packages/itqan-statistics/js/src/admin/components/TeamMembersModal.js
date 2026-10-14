import app from 'flarum/admin/app';
import Modal from 'flarum/common/components/Modal';
import Button from 'flarum/common/components/Button';
import LoadingIndicator from 'flarum/common/components/LoadingIndicator';

export default class TeamMembersModal extends Modal {
  oninit(vnode) {
    super.oninit(vnode);

    this.team = this.attrs.team;
    this.searchQuery = '';
    this.roleInput = '';
    this.searchResults = [];
    this.selectedUsers = {};
    this.loadingSearch = false;
    this.submitting = false;
    this.searchTimer = null;

    this.performSearch('');
  }

  className() {
    return 'ItqanStats-TeamMembersModal Modal--medium';
  }

  title() {
    return `${app.translator.trans('itqan-statistics.admin.teams.add_members_title')} — ${this.team.name}`;
  }

  performSearch(q) {
    this.loadingSearch = true;
    m.redraw();

    app
      .request({
        method: 'GET',
        url: `${app.forum.attribute('apiUrl')}/itqan-statistics/users`,
        params: { q, limit: 25 },
      })
      .then((res) => {
        this.searchResults = (res && res.users) || [];
        this.loadingSearch = false;
        m.redraw();
      })
      .catch(() => {
        this.loadingSearch = false;
        m.redraw();
      });
  }

  onSearchInput(value) {
    this.searchQuery = value;
    if (this.searchTimer) clearTimeout(this.searchTimer);
    this.searchTimer = setTimeout(() => {
      this.performSearch(this.searchQuery);
    }, 250);
  }

  toggleUser(user) {
    if (this.selectedUsers[user.id]) {
      delete this.selectedUsers[user.id];
    } else {
      this.selectedUsers[user.id] = user;
    }
  }

  content() {
    const existingIds = new Set((this.team.members || []).map((mem) => Number(mem.user_id)));
    const selectedCount = Object.keys(this.selectedUsers).length;

    return (
      <div className="Modal-body">
        <div className="Form-group">
          <input
            type="text"
            className="FormControl"
            placeholder={app.translator.trans('itqan-statistics.admin.teams.search_placeholder')}
            value={this.searchQuery}
            oninput={(e) => this.onSearchInput(e.target.value)}
          />
        </div>

        <div className="Form-group">
          <input
            type="text"
            className="FormControl"
            placeholder={app.translator.trans('itqan-statistics.admin.teams.role_placeholder')}
            value={this.roleInput}
            oninput={(e) => {
              this.roleInput = e.target.value;
            }}
          />
        </div>

        <div className="ItqanStats-userSearchList">
          {this.loadingSearch ? (
            <LoadingIndicator />
          ) : (
            this.searchResults.map((u) => {
              const alreadyMember = existingIds.has(Number(u.id));
              const isSelected = !!this.selectedUsers[u.id];

              return (
                <div
                  key={u.id}
                  className={`ItqanStats-userSearchRow ${isSelected ? 'is-selected' : ''} ${alreadyMember ? 'is-existing' : ''}`}
                  onclick={() => !alreadyMember && this.toggleUser(u)}
                >
                  <input
                    type="checkbox"
                    checked={isSelected || alreadyMember}
                    disabled={alreadyMember}
                    onchange={() => !alreadyMember && this.toggleUser(u)}
                  />
                  <div className="ItqanStats-userSearchMeta">
                    <strong>{u.display_name}</strong> <span className="ItqanStats-muted">@{u.username}</span>
                  </div>
                  <div className="ItqanStats-userSearchCounts">
                    <span>#{u.id}</span>
                  </div>
                </div>
              );
            })
          )}
        </div>

        <div className="Form-group" style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginTop: '16px' }}>
          <span className="ItqanStats-muted">{selectedCount}</span>
          <div style={{ display: 'flex', gap: '8px' }}>
            <Button className="Button" onclick={() => this.hide()}>
              {app.translator.trans('itqan-statistics.admin.teams.cancel')}
            </Button>
            <Button
              className="Button Button--primary"
              disabled={selectedCount === 0}
              loading={this.submitting}
              onclick={() => this.submitSelected()}
            >
              {app.translator.trans('itqan-statistics.admin.teams.add_selected')}
            </Button>
          </div>
        </div>
      </div>
    );
  }

  submitSelected() {
    const userIds = Object.keys(this.selectedUsers).map(Number);
    if (!userIds.length) return;

    this.submitting = true;
    app
      .request({
        method: 'POST',
        url: `${app.forum.attribute('apiUrl')}/itqan-statistics/teams/${this.team.id}/members`,
        body: {
          user_ids: userIds,
          role: this.roleInput.trim(),
        },
      })
      .then(() => {
        this.submitting = false;
        if (this.attrs.onUpdated) {
          this.attrs.onUpdated();
        }
        if (this.attrs.onSaved) {
          this.attrs.onSaved();
        }
        this.hide();
      })
      .catch(() => {
        this.submitting = false;
        m.redraw();
      });
  }
}
