import app from 'flarum/admin/app';
import Component from 'flarum/common/Component';
import Button from 'flarum/common/components/Button';
import LoadingIndicator from 'flarum/common/components/LoadingIndicator';
import icon from 'flarum/common/helpers/icon';

export default class TeamManagementSection extends Component {
  oninit(vnode) {
    super.oninit(vnode);

    this.selectedTeamId = null;
    this.isCreatingTeam = false;
    this.newTeamName = '';
    this.newTeamColor = '#0d9488';

    this.editingTeamId = null;
    this.editingTeamName = '';

    this.isAddingMember = false;
    this.memberSearchQuery = '';
    this.selectedUserIds = {};
    this.selectedUsersCache = {};
    this.forumUsers = [];
    this.loadingForumUsers = false;
    this.searchTimer = null;

    this.submitting = false;
  }

  tr(key, params = {}) {
    if (this.attrs.tr) {
      return this.attrs.tr(key, params);
    }
    return key;
  }

  getTeams() {
    return (this.attrs.teams || []).slice();
  }

  getSelectedTeam() {
    const teams = this.getTeams();
    if (!teams.length) return null;
    if (this.selectedTeamId) {
      const found = teams.find((tm) => Number(tm.id) === Number(this.selectedTeamId));
      if (found) return found;
    }
    this.selectedTeamId = teams[0].id;
    return teams[0];
  }

  refreshParent() {
    if (this.attrs.onUpdated) {
      this.attrs.onUpdated();
    }
  }

  createTeam() {
    const name = this.newTeamName.trim();
    if (!name || this.submitting) return;

    this.submitting = true;
    app
      .request({
        method: 'POST',
        url: `${app.forum.attribute('apiUrl')}/itqan-statistics/teams`,
        body: {
          name,
          color: this.newTeamColor || '#0d9488',
          is_visible: true,
        },
      })
      .then((res) => {
        this.submitting = false;
        this.newTeamName = '';
        this.isCreatingTeam = false;
        if (res && res.id) {
          this.selectedTeamId = res.id;
        }
        this.refreshParent();
      })
      .catch(() => {
        this.submitting = false;
        m.redraw();
      });
  }

  saveRenameTeam(team) {
    const name = this.editingTeamName.trim();
    if (!name || this.submitting) return;

    this.submitting = true;
    app
      .request({
        method: 'PATCH',
        url: `${app.forum.attribute('apiUrl')}/itqan-statistics/teams/${team.id}`,
        body: {
          name,
          description: team.description || '',
          color: team.color || '#0d9488',
          is_visible: team.is_visible,
        },
      })
      .then(() => {
        this.submitting = false;
        this.editingTeamId = null;
        this.editingTeamName = '';
        this.refreshParent();
      })
      .catch(() => {
        this.submitting = false;
        m.redraw();
      });
  }

  toggleVisibility(team, e) {
    if (e && e.stopPropagation) e.stopPropagation();
    if (this.submitting) return;

    this.submitting = true;
    app
      .request({
        method: 'PATCH',
        url: `${app.forum.attribute('apiUrl')}/itqan-statistics/teams/${team.id}`,
        body: {
          name: team.name,
          description: team.description || '',
          color: team.color || '#0d9488',
          is_visible: !team.is_visible,
        },
      })
      .then(() => {
        this.submitting = false;
        this.refreshParent();
      })
      .catch(() => {
        this.submitting = false;
        m.redraw();
      });
  }

  deleteTeam(team, e) {
    if (e && e.stopPropagation) e.stopPropagation();
    if (!window.confirm(this.tr('teams.confirm_delete'))) return;

    this.submitting = true;
    app
      .request({
        method: 'DELETE',
        url: `${app.forum.attribute('apiUrl')}/itqan-statistics/teams/${team.id}`,
      })
      .then(() => {
        this.submitting = false;
        if (Number(this.selectedTeamId) === Number(team.id)) {
          this.selectedTeamId = null;
        }
        this.refreshParent();
      })
      .catch(() => {
        this.submitting = false;
        m.redraw();
      });
  }

  openAddMemberDrawer() {
    this.isAddingMember = !this.isAddingMember;
    if (this.isAddingMember) {
      this.selectedUserIds = {};
      this.memberSearchQuery = '';
      this.searchForumUsers('');
    }
  }

  searchForumUsers(q) {
    this.loadingForumUsers = true;
    m.redraw();

    app
      .request({
        method: 'GET',
        url: `${app.forum.attribute('apiUrl')}/itqan-statistics/users`,
        params: { q, limit: 35 },
      })
      .then((res) => {
        const users = (res && res.users) || [];
        users.forEach((u) => {
          this.selectedUsersCache[u.id] = u;
        });
        this.forumUsers = users;
        this.loadingForumUsers = false;
        m.redraw();
      })
      .catch(() => {
        this.loadingForumUsers = false;
        m.redraw();
      });
  }

  onSearchInput(val) {
    this.memberSearchQuery = val;
    if (this.searchTimer) clearTimeout(this.searchTimer);
    this.searchTimer = setTimeout(() => {
      this.searchForumUsers(this.memberSearchQuery);
    }, 220);
  }

  toggleSelectUser(userId) {
    if (this.selectedUserIds[userId]) {
      delete this.selectedUserIds[userId];
    } else {
      this.selectedUserIds[userId] = true;
    }
  }

  submitAddMembers(team) {
    const ids = Object.keys(this.selectedUserIds).map(Number);
    if (!ids.length || !team || this.submitting) return;

    this.submitting = true;
    app
      .request({
        method: 'POST',
        url: `${app.forum.attribute('apiUrl')}/itqan-statistics/teams/${team.id}/members`,
        body: {
          user_ids: ids,
        },
      })
      .then(() => {
        this.submitting = false;
        this.selectedUserIds = {};
        this.isAddingMember = false;
        this.refreshParent();
      })
      .catch(() => {
        this.submitting = false;
        m.redraw();
      });
  }

  removeMember(teamId, userId) {
    if (this.submitting) return;
    this.submitting = true;

    app
      .request({
        method: 'DELETE',
        url: `${app.forum.attribute('apiUrl')}/itqan-statistics/teams/${teamId}/members/${userId}`,
      })
      .then(() => {
        this.submitting = false;
        this.refreshParent();
      })
      .catch(() => {
        this.submitting = false;
        m.redraw();
      });
  }

  view() {
    const teams = this.getTeams();
    const selectedTeam = this.getSelectedTeam();
    const isAr = !!this.attrs.isRtl;

    const existingMemberIds = new Set(
      ((selectedTeam && selectedTeam.members) || []).map((mem) => Number(mem.user_id))
    );
    const availableUsers = this.forumUsers.filter((u) => !existingMemberIds.has(Number(u.id)));
    const selectedIdsList = Object.keys(this.selectedUserIds).map(Number);

    return (
      <div className="ItqanStats-teamManagerGrid">
        {/* Left Sidebar: Available Teams */}
        <div className="ItqanStats-tmSidebar">
          <div className="ItqanStats-card ItqanStats-card--flush">
            <div className="ItqanStats-tmSidebarHeader">
              <h4>
                {icon('fas fa-shield-alt')}{' '}
                {isAr ? 'الفرق المتاحة' : 'Available Teams'}
              </h4>
              <Button
                className="Button Button--small Button--primary"
                icon="fas fa-plus"
                onclick={() => {
                  this.isCreatingTeam = !this.isCreatingTeam;
                }}
              >
                {this.tr('teams.create_team')}
              </Button>
            </div>

            {this.isCreatingTeam && (
              <div className="ItqanStats-tmCreateBox">
                <input
                  type="text"
                  className="FormControl"
                  placeholder={this.tr('teams.team_name')}
                  value={this.newTeamName}
                  oninput={(e) => {
                    this.newTeamName = e.target.value;
                  }}
                  onkeydown={(e) => {
                    if (e.key === 'Enter') this.createTeam();
                  }}
                />
                <div className="ItqanStats-tmCreateActions">
                  <Button
                    className="Button Button--small Button--primary"
                    loading={this.submitting}
                    onclick={() => this.createTeam()}
                  >
                    {this.tr('teams.save_team')}
                  </Button>
                  <Button
                    className="Button Button--small"
                    onclick={() => {
                      this.isCreatingTeam = false;
                      this.newTeamName = '';
                    }}
                  >
                    {this.tr('teams.cancel')}
                  </Button>
                </div>
              </div>
            )}

            <div className="ItqanStats-tmTeamList">
              {teams.length === 0 ? (
                <div className="ItqanStats-emptyState">
                  {this.tr('teams.no_teams')}
                </div>
              ) : (
                teams.map((tm) => {
                  const isSelected = selectedTeam && Number(selectedTeam.id) === Number(tm.id);
                  const isEditing = Number(this.editingTeamId) === Number(tm.id);

                  return (
                    <div
                      key={`tm-row-${tm.id}`}
                      className={`ItqanStats-tmTeamRow ${isSelected ? 'is-selected' : ''} ${
                        !tm.is_visible ? 'is-hidden-team' : ''
                      }`}
                      onclick={() => {
                        if (!isEditing) {
                          this.selectedTeamId = tm.id;
                          this.isAddingMember = false;
                        }
                      }}
                    >
                      {isEditing ? (
                        <div
                          className="ItqanStats-tmInlineEdit"
                          onclick={(e) => e.stopPropagation()}
                        >
                          <input
                            type="text"
                            className="FormControl"
                            value={this.editingTeamName}
                            oninput={(e) => {
                              this.editingTeamName = e.target.value;
                            }}
                            onkeydown={(e) => {
                              if (e.key === 'Enter') this.saveRenameTeam(tm);
                            }}
                          />
                          <Button
                            className="Button Button--icon Button--small Button--primary"
                            icon="fas fa-check"
                            onclick={() => this.saveRenameTeam(tm)}
                          />
                          <Button
                            className="Button Button--icon Button--small"
                            icon="fas fa-times"
                            onclick={() => {
                              this.editingTeamId = null;
                            }}
                          />
                        </div>
                      ) : (
                        <div className="ItqanStats-tmTeamRowInner">
                          <div className="ItqanStats-tmTeamInfo">
                            <span className="ItqanStats-tmTeamName">{tm.name}</span>
                            <span className="ItqanStats-tmTeamMeta">
                              {icon('fas fa-users')}{' '}
                              {this.tr('teams.members_count', { count: tm.member_count })}
                              {!tm.is_visible && (
                                <span className="ItqanStats-tmHiddenTag">
                                  ({isAr ? 'مخفي' : 'Hidden'})
                                </span>
                              )}
                            </span>
                          </div>

                          <div
                            className="ItqanStats-tmRowButtons"
                            onclick={(e) => e.stopPropagation()}
                          >
                            <button
                              type="button"
                              className={`ItqanStats-tmIconBtn ${
                                tm.is_visible ? 'is-visible' : 'is-muted'
                              }`}
                              title={
                                tm.is_visible
                                  ? isAr
                                    ? 'ظاهر في الإحصائيات العامة (اضغط للإخفاء)'
                                    : 'Visible in General Stats (Click to hide)'
                                  : isAr
                                  ? 'مخفي من الإحصائيات العامة (اضغط للإظهار)'
                                  : 'Hidden from General Stats (Click to show)'
                              }
                              onclick={(e) => this.toggleVisibility(tm, e)}
                            >
                              {icon(tm.is_visible ? 'fas fa-eye' : 'fas fa-eye-slash')}
                            </button>

                            <button
                              type="button"
                              className="ItqanStats-tmIconBtn"
                              title={this.tr('teams.edit_team')}
                              onclick={() => {
                                this.editingTeamId = tm.id;
                                this.editingTeamName = tm.name;
                              }}
                            >
                              {icon('fas fa-pen')}
                            </button>

                            <button
                              type="button"
                              className="ItqanStats-tmIconBtn ItqanStats-tmIconBtn--danger"
                              title={this.tr('teams.delete_team')}
                              onclick={(e) => this.deleteTeam(tm, e)}
                            >
                              {icon('fas fa-trash')}
                            </button>
                          </div>
                        </div>
                      )}
                    </div>
                  );
                })
              )}
            </div>
          </div>
        </div>

        {/* Right Detail Panel: Selected Team Members & Inline Multi-Select Member Picker */}
        <div className="ItqanStats-tmDetailPanel">
          {selectedTeam ? (
            <div className="ItqanStats-card ItqanStats-card--flush">
              <div className="ItqanStats-cardBannerHeader ItqanStats-cardBannerHeader--split">
                <div>
                  <h3>
                    {icon('fas fa-users')} {selectedTeam.name}
                    <span className="ItqanStats-countPill">{selectedTeam.member_count}</span>
                  </h3>
                  <p className="ItqanStats-cardHelp">
                    {isAr
                      ? 'إدارة الأعضاء المنتمين لهذا الفريق لاحتساب إحصائياتهم المجمعة'
                      : 'Manage members assigned to this team to track their aggregated statistics'}
                  </p>
                </div>

                <Button
                  className="Button Button--primary"
                  icon="fas fa-user-plus"
                  onclick={() => this.openAddMemberDrawer()}
                >
                  {this.tr('teams.add_members')}
                </Button>
              </div>

              {/* Inline Add Members Drawer (matches automations TeamManagement) */}
              {this.isAddingMember && (
                <div className="ItqanStats-tmAddDrawer">
                  <div className="ItqanStats-tmAddDrawerTop">
                    <strong>
                      {isAr ? 'اختر الأعضاء من المنتدى' : 'Select members from the forum'}
                    </strong>
                    {selectedIdsList.length > 0 && (
                      <span className="ItqanStats-tmSelectedCountBadge">
                        {isAr
                          ? `تم تحديد ${selectedIdsList.length} عضو`
                          : `${selectedIdsList.length} selected`}
                      </span>
                    )}
                  </div>

                  {/* Selected User Chips */}
                  {selectedIdsList.length > 0 && (
                    <div className="ItqanStats-tmSelectedChips">
                      {selectedIdsList.map((uid) => {
                        const u = this.selectedUsersCache[uid] || {
                          id: uid,
                          display_name: `User #${uid}`,
                        };
                        return (
                          <span key={`chip-${uid}`} className="ItqanStats-tmUserChip">
                            <span>{u.display_name}</span>
                            <button
                              type="button"
                              onclick={() => this.toggleSelectUser(uid)}
                            >
                              {icon('fas fa-times')}
                            </button>
                          </span>
                        );
                      })}
                    </div>
                  )}

                  {/* Search Input */}
                  <div className="ItqanStats-tmSearchWrap">
                    <input
                      type="text"
                      className="FormControl"
                      placeholder={this.tr('teams.search_placeholder')}
                      value={this.memberSearchQuery}
                      oninput={(e) => this.onSearchInput(e.target.value)}
                    />
                  </div>

                  {/* User Picker List */}
                  <div className="ItqanStats-userSearchList">
                    {this.loadingForumUsers ? (
                      <div className="ItqanStats-loadingWrap">
                        <LoadingIndicator />
                      </div>
                    ) : availableUsers.length === 0 ? (
                      <div className="ItqanStats-emptyState">
                        {isAr ? 'لا توجد نتائج مطابقة' : 'No matching users found'}
                      </div>
                    ) : (
                      availableUsers.map((u) => {
                        const isChecked = !!this.selectedUserIds[u.id];
                        return (
                          <div
                            key={`pick-${u.id}`}
                            className={`ItqanStats-userSearchRow ${isChecked ? 'is-selected' : ''}`}
                            onclick={() => this.toggleSelectUser(u.id)}
                          >
                            <input
                              type="checkbox"
                              checked={isChecked}
                              onchange={() => this.toggleSelectUser(u.id)}
                            />
                            <div className="ItqanStats-userSearchMeta">
                              <strong>{u.display_name}</strong>{' '}
                              <span className="ItqanStats-muted">@{u.username}</span>
                            </div>
                            <span className="ItqanStats-userSearchCounts">#{u.id}</span>
                          </div>
                        );
                      })
                    )}
                  </div>

                  <div className="ItqanStats-tmDrawerFooter">
                    <Button
                      className="Button Button--primary"
                      icon="fas fa-check"
                      disabled={selectedIdsList.length === 0}
                      loading={this.submitting}
                      onclick={() => this.submitAddMembers(selectedTeam)}
                    >
                      {this.tr('teams.add_selected')} ({selectedIdsList.length})
                    </Button>
                    <Button
                      className="Button"
                      onclick={() => {
                        this.isAddingMember = false;
                        this.selectedUserIds = {};
                      }}
                    >
                      {this.tr('teams.cancel')}
                    </Button>
                  </div>
                </div>
              )}

              {/* Members Grid */}
              <div className="ItqanStats-tmMembersBody">
                {!selectedTeam.members || selectedTeam.members.length === 0 ? (
                  <div className="ItqanStats-emptyState">
                    {this.tr('teams.no_members')}
                  </div>
                ) : (
                  <div className="ItqanStats-tmMembersCardsGrid">
                    {selectedTeam.members.map((member) => (
                      <div
                        key={`mem-card-${member.user_id}`}
                        className="ItqanStats-tmMemberCard"
                      >
                        <div className="ItqanStats-tmMemberCardLeft">
                          <div className="ItqanStats-tmAvatar">
                            {String(member.display_name || member.username || '?')
                              .slice(0, 2)
                              .toUpperCase()}
                          </div>
                          <div className="ItqanStats-tmMemberNames">
                            <strong>{member.display_name}</strong>
                            <span>@{member.username}</span>
                          </div>
                        </div>

                        <button
                          type="button"
                          className="ItqanStats-tmRemoveMemberBtn"
                          title={this.tr('teams.remove_member')}
                          onclick={() => this.removeMember(selectedTeam.id, member.user_id)}
                        >
                          {icon('fas fa-user-minus')}
                        </button>
                      </div>
                    ))}
                  </div>
                )}
              </div>
            </div>
          ) : (
            <div className="ItqanStats-card ItqanStats-emptyState">
              {isAr
                ? 'اختر فريقاً من القائمة الجانبية لعرض وإدارة أعضائه'
                : 'Select a team from the sidebar to view and manage its members'}
            </div>
          )}
        </div>
      </div>
    );
  }
}
