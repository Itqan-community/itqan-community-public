import app from 'flarum/admin/app';
import ExtensionPage from 'flarum/admin/components/ExtensionPage';
import Button from 'flarum/common/components/Button';
import LoadingIndicator from 'flarum/common/components/LoadingIndicator';
import Select from 'flarum/common/components/Select';
import Switch from 'flarum/common/components/Switch';
import icon from '../../common/icon';

import {
  formatNumber,
  renderLineComparisonChart,
  renderStackedBarChart,
  renderTeamMetricBarChart,
} from '../utils/charts';
import { t } from '../utils/i18n';
import TeamModal from './TeamModal';
import TeamMembersModal from './TeamMembersModal';
import TeamManagementSection from './TeamManagementSection';

const PERIOD_PRESETS = [
  'mtd',
  'last_day',
  'last_7_days',
  'last_30_days',
  'last_90_days',
  'last_365_days',
  'all_time',
  'custom',
];

const TREND_METRICS = [
  { key: 'users', iconName: 'fas fa-users', color: '#10b981', themeClass: 'emerald' },
  { key: 'discussions', iconName: 'fas fa-comments', color: '#2563eb', themeClass: 'blue' },
  { key: 'posts', iconName: 'fas fa-comment-alt', color: '#8b5cf6', themeClass: 'purple' },
  { key: 'upvotes', iconName: 'fas fa-thumbs-up', color: '#059669', themeClass: 'teal' },
  { key: 'downvotes', iconName: 'fas fa-thumbs-down', color: '#e11d48', themeClass: 'rose' },
];

const ENGAGEMENT_SEGMENTS = [
  { key: 'lurkers', iconName: 'fas fa-eye', color: '#f59e0b', themeClass: 'amber' },
  { key: 'returning_readers', iconName: 'fas fa-book-open', color: '#06b6d4', themeClass: 'cyan' },
  { key: 'active_1', iconName: 'fas fa-thumbs-up', color: '#10b981', themeClass: 'emerald' },
  { key: 'with_posts', iconName: 'fas fa-comment-dots', color: '#0d9488', themeClass: 'teal' },
  { key: 'active_3', iconName: 'fas fa-fire', color: '#3b82f6', themeClass: 'blue' },
  { key: 'active_10', iconName: 'fas fa-bolt', color: '#8b5cf6', themeClass: 'purple' },
];

export default class ItqanStatisticsPage extends ExtensionPage {
  oninit(vnode) {
    super.oninit(vnode);

    this.activeSection = 'dashboard'; // 'dashboard' | 'teams' | 'settings'
    this.selectedPeriod = 'mtd'; // Default: Current month-to-date
    this.customFrom = new Date(new Date().getFullYear(), new Date().getMonth(), 1).toISOString().slice(0, 10);
    this.customTo = new Date().toISOString().slice(0, 10);

    this.selectedTrendMetric = 'users'; // 'users' | 'discussions' | 'posts' | 'upvotes' | 'downvotes'
    this.visibleEngagementSeries = {
      lurkers: true,
      returning_readers: true,
      active_1: true,
      with_posts: true,
      active_3: true,
      active_10: true,
    };

    this.selectedTeamFilter = 'all'; // 'all' | String(team.id)
    this.showHiddenTeams = false;
    this.unifiedSort = { col: 'total', dir: 'desc' };

    this.loading = true;
    this.refreshing = false;
    this.rebuilding = false;
    this.data = null;
    this.langOverride = null; // null (auto from Flarum) | 'ar' | 'en'
  }

  oncreate(vnode) {
    super.oncreate(vnode);
    this.loadDashboard(false);
  }

  tr(shortKey, params = {}) {
    return t(shortKey, params, this.getActiveLang());
  }

  getActiveLang() {
    if (this.langOverride) {
      return this.langOverride;
    }
    const loc = (app.translator && app.translator.getLocale && app.translator.getLocale()) || 'en';
    return String(loc).startsWith('ar') ? 'ar' : 'en';
  }

  isArabicLocale() {
    return this.getActiveLang() === 'ar';
  }

  loadDashboard(forceRefresh = false) {
    if (forceRefresh) {
      this.refreshing = true;
    } else {
      this.loading = true;
    }
    m.redraw();

    const params = { period: this.selectedPeriod };
    if (this.selectedPeriod === 'custom') {
      params.from = this.customFrom;
      params.to = this.customTo;
    }
    if (forceRefresh) {
      params.refresh = 1;
    }

    app
      .request({
        method: 'GET',
        url: `${app.forum.attribute('apiUrl')}/itqan-statistics/dashboard`,
        params,
      })
      .then((res) => {
        this.data = res;
        this.loading = false;
        this.refreshing = false;
        m.redraw();
      })
      .catch((err) => {
        console.error(err);
        this.loading = false;
        this.refreshing = false;
        m.redraw();
      });
  }

  changePeriod(period) {
    this.selectedPeriod = period;
    if (period !== 'custom') {
      this.loadDashboard(false);
    }
  }

  renderDeltaChip(metricSummary) {
    if (!metricSummary || metricSummary.status === 'none') return null;

    if (metricSummary.status === 'new') {
      return (
        <span
          className="ItqanStats-deltaChip ItqanStats-deltaChip--up"
          title={this.tr('trend.delta_tooltip')}
        >
          {icon('fas fa-chart-line')} {this.tr('trend.delta_new')}
        </span>
      );
    }

    const pct = metricSummary.delta_pct || 0;
    const isUp = pct >= 0;
    const cls = isUp ? 'up' : 'down';

    return (
      <span
        className={`ItqanStats-deltaChip ItqanStats-deltaChip--${cls}`}
        title={this.tr('trend.delta_tooltip')}
      >
        {isUp ? icon('fas fa-chart-line') : icon('fas fa-level-down-alt')} {Math.abs(pct)}%
      </span>
    );
  }

  content() {
    const isRtl = this.isArabicLocale();
    const dir = isRtl ? 'rtl' : 'ltr';
    const activeLang = this.getActiveLang();

    const periodOptions = {};
    PERIOD_PRESETS.forEach((p) => {
      periodOptions[p] = this.tr(`period.${p}`);
    });

    return (
      <div className={`ItqanStatisticsPage ${isRtl ? 'is-rtl' : 'is-ltr'}`} dir={dir}>
        <div className="container">
          {/* Top Header & Navigation Tabs */}
          <div className="ItqanStats-topBar">
            <div className="ItqanStats-titleGroup">
              <h2>
                {icon('fas fa-chart-bar')} {this.tr('title')}
              </h2>
              <p className="ItqanStats-subtitle">{this.tr('subtitle')}</p>
            </div>

            <div className="ItqanStats-navTabs">
              {/* Bilingual AR (RTL) / EN (LTR) Switcher */}
              <div className="ItqanStats-langSwitchGroup">
                <button
                  type="button"
                  className={`ItqanStats-langBtn ${activeLang === 'ar' ? 'is-active' : ''}`}
                  onclick={() => {
                    this.langOverride = 'ar';
                  }}
                >
                  العربية (RTL)
                </button>
                <button
                  type="button"
                  className={`ItqanStats-langBtn ${activeLang === 'en' ? 'is-active' : ''}`}
                  onclick={() => {
                    this.langOverride = 'en';
                  }}
                >
                  English (LTR)
                </button>
              </div>

              <Button
                className={`Button ${this.activeSection === 'dashboard' ? 'Button--primary' : ''}`}
                icon="fas fa-chart-line"
                onclick={() => {
                  this.activeSection = 'dashboard';
                }}
              >
                {this.tr('nav.dashboard')}
              </Button>
              <Button
                className={`Button ${this.activeSection === 'teams' ? 'Button--primary' : ''}`}
                icon="fas fa-users-cog"
                onclick={() => {
                  this.activeSection = 'teams';
                }}
              >
                {this.tr('nav.teams')}
              </Button>
              <Button
                className={`Button ${this.activeSection === 'settings' ? 'Button--primary' : ''}`}
                icon="fas fa-sliders-h"
                onclick={() => {
                  this.activeSection = 'settings';
                }}
              >
                {this.tr('nav.settings')}
              </Button>
            </div>
          </div>

          {/* Section 4.1: Period Selector Bar + Quick Refresh Button */}
          {this.activeSection !== 'settings' && (
            <div className="ItqanStats-periodBar">
              <div className="ItqanStats-periodControls">
                <label className="ItqanStats-periodLabel">
                  {icon('far fa-calendar-alt')} {this.tr('period.label')}:
                </label>
                <Select
                  value={this.selectedPeriod}
                  options={periodOptions}
                  onchange={(val) => this.changePeriod(val)}
                />

                {this.selectedPeriod === 'custom' && (
                  <div className="ItqanStats-customRangeInputs">
                    <span className="ItqanStats-customRangeLabel">{this.tr('period.from')}:</span>
                    <input
                      type="date"
                      className="FormControl"
                      value={this.customFrom}
                      onchange={(e) => {
                        this.customFrom = e.target.value;
                      }}
                    />
                    <span className="ItqanStats-customRangeLabel">{this.tr('period.to')}:</span>
                    <input
                      type="date"
                      className="FormControl"
                      value={this.customTo}
                      onchange={(e) => {
                        this.customTo = e.target.value;
                      }}
                    />
                    <Button
                      className="Button Button--primary"
                      icon="fas fa-check"
                      onclick={() => this.loadDashboard(false)}
                    >
                      {this.tr('period.apply')}
                    </Button>
                  </div>
                )}

                <Button
                  className="Button ItqanStats-refreshBtn"
                  icon="fas fa-sync-alt"
                  loading={this.refreshing}
                  onclick={() => this.loadDashboard(true)}
                >
                  {this.refreshing ? this.tr('period.refreshing') : this.tr('period.refresh')}
                </Button>
              </div>
            </div>
          )}

          {this.loading ? (
            <div className="ItqanStats-loadingWrap">
              <LoadingIndicator size="large" />
            </div>
          ) : !this.data ? (
            <div className="ItqanStats-card">Failed to load statistics data.</div>
          ) : (
            <div className="ItqanStats-viewWrapper">
              {this.activeSection === 'dashboard' && this.renderDashboardView(isRtl)}
              {this.activeSection === 'teams' && (
                <div className="ItqanStats-dashboardStack">
                  <TeamManagementSection
                    teams={this.data.teams || []}
                    isRtl={isRtl}
                    tr={(k, params) => this.tr(k, params)}
                    onUpdated={() => this.loadDashboard(false)}
                  />
                  {this.renderTeamsSection(true, isRtl)}
                </div>
              )}
              {this.activeSection === 'settings' && this.renderSettingsSection()}
            </div>
          )}
        </div>
      </div>
    );
  }

  renderDashboardView(isRtl) {
    return (
      <div className="ItqanStats-dashboardStack">
        {/* Section 1: Daily Community Activity Over Time (5 metrics: Users, Discussions, Posts, Upvotes, Downvotes) */}
        {this.renderDailyTrendSection(isRtl)}

        {/* Section 2: Member Engagement Levels (6 color-tinted cards) + 12-Month Engagement Bar Chart with Interactive Toggles */}
        {this.renderEngagementAndMonthlySection(isRtl)}

        {/* Section 3: Team Statistics (Team Filter Tabs + 3 Cards with 12-Month Bar Charts per Team + Unified Member Table) */}
        {this.renderTeamsSection(false, isRtl)}
      </div>
    );
  }

  renderDailyTrendSection(isRtl) {
    const { lifetime, period_summary, daily_trend, window: win } = this.data;

    const currentSeries = daily_trend.current || [];
    const previousSeries = daily_trend.previous || [];

    const cappedCurrent = currentSeries.slice(-730);
    const cappedPrevious = previousSeries.slice(-730);

    const activeMetricObj =
      TREND_METRICS.find((tm) => tm.key === this.selectedTrendMetric) || TREND_METRICS[0];

    const labels = cappedCurrent.map((d) => d.date.slice(5)); // MM-DD
    const currentValues = cappedCurrent.map((d) => Number(d[activeMetricObj.key]) || 0);
    const previousValues = cappedPrevious.map((d) => Number(d[activeMetricObj.key]) || 0);

    return (
      <div className="ItqanStats-card ItqanStats-card--flush">
        <div className="ItqanStats-cardBannerHeader">
          <div className="ItqanStats-cardTitleRow">
            <h3>
              {icon('fas fa-wave-square')} {this.tr('trend.heading')}
            </h3>
            <p className="ItqanStats-cardHelp">{this.tr('trend.help')}</p>
          </div>

          {win && (
            <div className="ItqanStats-windowChips">
              <span className="ItqanStats-windowChip ItqanStats-windowChip--primary">
                {icon('far fa-calendar-alt')}{' '}
                {this.tr('period.displayed_window', {
                  from: win.from,
                  to: win.to,
                  days: win.days,
                })}
              </span>
              {win.comparison_enabled && (
                <span className="ItqanStats-windowChip">
                  {icon('far fa-calendar')}{' '}
                  {this.tr('period.comparison_window', {
                    from: win.prev_from,
                    to: win.prev_to,
                    days: win.days,
                  })}
                </span>
              )}
            </div>
          )}
        </div>

        {/* 5 Metric Tabs (Users, Discussions, Posts, Upvotes, Downvotes) */}
        <div className="ItqanStats-metricPillsBar">
          {TREND_METRICS.map((tm) => {
            const isActive = this.selectedTrendMetric === tm.key;
            const summary = (period_summary && period_summary[tm.key]) || { current: 0, status: 'none' };
            const totalAllTime = lifetime ? lifetime[tm.key] : null;

            return (
              <button
                key={tm.key}
                type="button"
                className={`ItqanStats-metricPill ItqanStats-metricPill--${tm.themeClass} ${
                  isActive ? 'is-active' : ''
                }`}
                onclick={() => {
                  this.selectedTrendMetric = tm.key;
                }}
              >
                <div className="ItqanStats-metricPillRow">
                  <span className="ItqanStats-metricPillIcon">{icon(tm.iconName)}</span>
                  <span className="ItqanStats-metricPillLabel">{this.tr(`trend.${tm.key}`)}</span>
                  <span className="ItqanStats-metricPillCount">{formatNumber(summary.current)}</span>
                  {this.renderDeltaChip(summary)}
                </div>
                {typeof totalAllTime === 'number' && (
                  <div className="ItqanStats-metricPillLifetime">
                    {this.tr('trend.since_start', {
                      count: formatNumber(totalAllTime),
                    })}
                  </div>
                )}
              </button>
            );
          })}
        </div>

        <div className="ItqanStats-cardChartBody">
          {cappedCurrent.length === 0 ? (
            <div className="ItqanStats-emptyState">{this.tr('trend.no_data')}</div>
          ) : (
            renderLineComparisonChart({
              labels,
              currentValues,
              previousValues,
              currentLabel: this.tr(`trend.${activeMetricObj.key}_series`),
              previousLabel: this.tr('trend.previous_series'),
              primaryColor: activeMetricObj.color,
              isRtl,
              gradientId: `itqanDailyGrad_${activeMetricObj.key}`,
              height: 280,
            })
          )}
        </div>
      </div>
    );
  }

  renderEngagementAndMonthlySection(isRtl) {
    const eng = this.data.engagement || {};
    const rolling = this.data.rolling_12_months || [];
    const labels = rolling.map((mo) => (isRtl ? mo.label_ar : mo.label_en));

    const toggleSeries = (key) => {
      this.visibleEngagementSeries[key] = !this.visibleEngagementSeries[key];
    };

    const activeEngagementSeries = ENGAGEMENT_SEGMENTS.filter(
      (seg) => this.visibleEngagementSeries[seg.key]
    ).map((seg) => ({
      key: seg.key,
      name: this.tr(`engagement.${seg.key}`),
      color: seg.color,
      values: rolling.map((mo) => (mo.engagement ? Number(mo.engagement[seg.key]) || 0 : 0)),
    }));

    return (
      <div className="ItqanStats-sectionBlock">
        {/* 6 Engagement Level Cards (Lurkers -> Returning Readers -> Active 1 -> With Posts -> 3+ -> 10+) */}
        <div className="ItqanStats-engagementGrid">
          {ENGAGEMENT_SEGMENTS.map((seg) => (
            <div
              className={`ItqanStats-engagementCard ItqanStats-engagementCard--${seg.themeClass}`}
              key={seg.key}
            >
              <div className="ItqanStats-engagementTop">
                <span className="ItqanStats-engagementTitle">{this.tr(`engagement.${seg.key}`)}</span>
                <span className="ItqanStats-engagementIconWrap">{icon(seg.iconName)}</span>
              </div>
              <div className="ItqanStats-engagementValue">{formatNumber(eng[seg.key] || 0)}</div>
              <div className="ItqanStats-engagementDesc">{this.tr(`engagement.${seg.key}_desc`)}</div>
            </div>
          ))}
        </div>

        {/* Unified 12-Month Engagement & Readers Bar Chart with Interactive Series Toggle Pills */}
        <div className="ItqanStats-card ItqanStats-card--flush ItqanStats-mt16">
          <div className="ItqanStats-cardBannerHeader ItqanStats-cardBannerHeader--indigo">
            <div className="ItqanStats-cardTitleRow">
              <h3>
                {icon('fas fa-sliders-h')} {this.tr('rolling.heading')}
              </h3>
              <p className="ItqanStats-cardHelp">{this.tr('rolling.help')}</p>
            </div>
          </div>

          {/* Interactive Series Toggles */}
          <div className="ItqanStats-seriesToggleBar">
            {ENGAGEMENT_SEGMENTS.map((seg) => {
              const isVisible = !!this.visibleEngagementSeries[seg.key];
              return (
                <button
                  key={seg.key}
                  type="button"
                  onclick={() => toggleSeries(seg.key)}
                  className={`ItqanStats-seriesToggleBtn ItqanStats-seriesToggleBtn--${seg.themeClass} ${
                    isVisible ? 'is-visible' : 'is-hidden'
                  }`}
                >
                  <span
                    className="ItqanStats-seriesDot"
                    style={{ backgroundColor: isVisible ? seg.color : '#94a3b8' }}
                  />
                  <span>{this.tr(`engagement.${seg.key}`)}</span>
                </button>
              );
            })}
          </div>

          <div className="ItqanStats-cardChartBody">
            {renderStackedBarChart({
              labels,
              series: activeEngagementSeries,
              stacked: false,
              isRtl,
              height: 300,
            })}
          </div>
        </div>
      </div>
    );
  }

  renderTeamsSection(fullManagementMode = false, isRtl = false) {
    const allTeams = this.data.teams || [];
    const visibleTeams =
      fullManagementMode || this.showHiddenTeams ? allTeams : allTeams.filter((tm) => tm.is_visible);

    const hasSelectedTeam =
      this.selectedTeamFilter === 'all' ||
      visibleTeams.some((tm) => String(tm.id) === String(this.selectedTeamFilter));
    const currentFilter = hasSelectedTeam ? this.selectedTeamFilter : 'all';

    const displayedTeams =
      currentFilter === 'all'
        ? visibleTeams
        : visibleTeams.filter((tm) => String(tm.id) === String(currentFilter));

    return (
      <div className="ItqanStats-teamsSectionWrap">
        {/* Teams Section Header + Team Filter Tabs */}
        <div className="ItqanStats-teamsTopHeader">
          <div>
            <h3 className="ItqanStats-teamsMainTitle">
              {icon('fas fa-shield-alt')} {this.tr('teams.heading')}
            </h3>
            <p className="ItqanStats-cardHelp">{this.tr('teams.help')}</p>
          </div>

          <div className="ItqanStats-teamHeaderActions">
            {visibleTeams.length > 0 && (
              <div className="ItqanStats-teamFilterPills">
                <button
                  type="button"
                  className={`ItqanStats-teamFilterPill ${currentFilter === 'all' ? 'is-active' : ''}`}
                  onclick={() => {
                    this.selectedTeamFilter = 'all';
                  }}
                >
                  {this.tr('teams.all_teams')}
                </button>
                {visibleTeams.map((team) => (
                  <button
                    key={`tab-${team.id}`}
                    type="button"
                    className={`ItqanStats-teamFilterPill ${
                      String(currentFilter) === String(team.id) ? 'is-active' : ''
                    }`}
                    onclick={() => {
                      this.selectedTeamFilter = String(team.id);
                    }}
                  >
                    {team.name} ({team.member_count})
                  </button>
                ))}
              </div>
            )}

            <Switch
              state={this.showHiddenTeams}
              onchange={(val) => {
                this.showHiddenTeams = val;
              }}
            >
              {this.tr('teams.show_hidden')}
            </Switch>

            <Button
              className="Button Button--primary"
              icon="fas fa-plus"
              onclick={() => {
                app.modal.show(TeamModal, {
                  onSaved: () => this.loadDashboard(false),
                });
              }}
            >
              {this.tr('teams.create_team')}
            </Button>
          </div>
        </div>

        {visibleTeams.length === 0 ? (
          <div className="ItqanStats-card ItqanStats-emptyState">
            <p>{this.tr('teams.no_teams')}</p>
          </div>
        ) : (
          <div className="ItqanStats-teamsContentStack">
            {/* Per-Team 3-Metric Cards with Embedded 12-Month Bar Charts */}
            <div className="ItqanStats-teamsOverviewList">
              {displayedTeams.map((team) => this.renderTeamOverviewRow(team, isRtl))}
            </div>

            {/* Unified Team Members Contribution Breakdown Table */}
            {this.renderUnifiedMembersTable(visibleTeams, currentFilter)}
          </div>
        )}
      </div>
    );
  }

  renderTeamOverviewRow(team, isRtl) {
    const trend = team.monthly_trend || [];
    const labels = trend.map((mo) => (isRtl ? mo.label_ar : mo.label_en));
    const forumLabel = this.tr('teams.forum_label');

    const discForum = trend.map((mo) => mo.forum_discussions);
    const discTeam = trend.map((mo) => mo.team_discussions);

    const repForum = trend.map((mo) => mo.forum_replies);
    const repTeam = trend.map((mo) => mo.team_replies);

    const totForum = trend.map((mo) => mo.forum_total);
    const totTeam = trend.map((mo) => mo.team_total);

    return (
      <div className="ItqanStats-teamOverviewBlock" key={`overview-${team.id}`}>
        <div className="ItqanStats-teamBlockHeader">
          <div className="ItqanStats-teamTitleGroup">
            <span className="ItqanStats-teamNameBadge">{team.name}</span>
            <span className="ItqanStats-teamMembersCount">
              ({this.tr('teams.members_count', { count: team.member_count })})
            </span>
            {!team.is_visible && (
              <span className="ItqanStats-teamHiddenBadge">
                {icon('fas fa-eye-slash')} Hidden
              </span>
            )}
            {team.description && <span className="ItqanStats-teamDescInline">— {team.description}</span>}
          </div>

          <div className="ItqanStats-teamCardButtons">
            <Button
              className="Button Button--small"
              icon="fas fa-user-plus"
              onclick={() => {
                app.modal.show(TeamMembersModal, {
                  team,
                  onUpdated: () => this.loadDashboard(false),
                });
              }}
            >
              {this.tr('teams.add_members')}
            </Button>
            <Button
              className="Button Button--small"
              icon="fas fa-edit"
              onclick={() => {
                app.modal.show(TeamModal, {
                  team,
                  onSaved: () => this.loadDashboard(false),
                });
              }}
            >
              {this.tr('teams.edit_team')}
            </Button>
            <Button
              className="Button Button--small Button--danger"
              icon="fas fa-trash"
              onclick={() => this.deleteTeam(team)}
            />
          </div>
        </div>

        {/* 3 Metric Cards per Team (Discussions, Replies, Total Interactions) each with its own 12-Month Team vs Forum Bar Chart */}
        <div className="ItqanStats-teamThreeCardsGrid">
          {/* Card 1: Discussions */}
          <div className="ItqanStats-teamMetricCard">
            <div className="ItqanStats-teamMetricCardHeader">
              <div>
                <div className="ItqanStats-teamMetricCardTitle">{this.tr('teams.discussions_started')}</div>
                <div className="ItqanStats-teamMetricCardValue">
                  {formatNumber(team.metrics.discussions)}
                </div>
              </div>
              <div className="ItqanStats-teamShareCol">
                <span className="ItqanStats-teamShareBadge ItqanStats-teamShareBadge--emerald">
                  {team.metrics.share_discussions_pct}%
                </span>
                <span className="ItqanStats-teamShareSub">
                  {this.tr('teams.share_discussions', {
                    total: formatNumber(team.metrics.forum_discussions || 0),
                  })}
                </span>
              </div>
            </div>
            {renderTeamMetricBarChart({
              labels,
              forumValues: discForum,
              teamValues: discTeam,
              forumColor: '#3b82f6',
              teamColor: '#10b981',
              forumLabel,
              teamLabel: team.name,
              isRtl,
            })}
          </div>

          {/* Card 2: Replies */}
          <div className="ItqanStats-teamMetricCard">
            <div className="ItqanStats-teamMetricCardHeader">
              <div>
                <div className="ItqanStats-teamMetricCardTitle">{this.tr('teams.replies_posted')}</div>
                <div className="ItqanStats-teamMetricCardValue">
                  {formatNumber(team.metrics.replies)}
                </div>
              </div>
              <div className="ItqanStats-teamShareCol">
                <span className="ItqanStats-teamShareBadge ItqanStats-teamShareBadge--indigo">
                  {team.metrics.share_replies_pct}%
                </span>
                <span className="ItqanStats-teamShareSub">
                  {this.tr('teams.share_replies', {
                    total: formatNumber(team.metrics.forum_replies || 0),
                  })}
                </span>
              </div>
            </div>
            {renderTeamMetricBarChart({
              labels,
              forumValues: repForum,
              teamValues: repTeam,
              forumColor: '#8b5cf6',
              teamColor: '#06b6d4',
              forumLabel,
              teamLabel: team.name,
              isRtl,
            })}
          </div>

          {/* Card 3: Total Interactions */}
          <div className="ItqanStats-teamMetricCard ItqanStats-teamMetricCard--highlight">
            <div className="ItqanStats-teamMetricCardHeader">
              <div>
                <div className="ItqanStats-teamMetricCardTitle ItqanStats-teamMetricCardTitle--primary">
                  {this.tr('teams.total_activity')}
                </div>
                <div className="ItqanStats-teamMetricCardValue">
                  {formatNumber(team.metrics.total)}
                </div>
              </div>
              <div className="ItqanStats-teamShareCol">
                <span className="ItqanStats-teamShareBadge ItqanStats-teamShareBadge--amber">
                  {team.metrics.share_total_pct}%
                </span>
                <span className="ItqanStats-teamShareSub">
                  {this.tr('teams.share_total', {
                    total: formatNumber(team.metrics.forum_total || 0),
                  })}
                </span>
              </div>
            </div>
            {renderTeamMetricBarChart({
              labels,
              forumValues: totForum,
              teamValues: totTeam,
              forumColor: '#64748b',
              teamColor: '#f59e0b',
              forumLabel,
              teamLabel: team.name,
              isRtl,
            })}
          </div>
        </div>
      </div>
    );
  }

  renderUnifiedMembersTable(visibleTeams, currentFilter) {
    const rawMembers = [];
    const seenUsers = new Map();

    const sourceTeams =
      currentFilter === 'all'
        ? visibleTeams
        : visibleTeams.filter((tm) => String(tm.id) === String(currentFilter));

    sourceTeams.forEach((team) => {
      (team.members || []).forEach((mem) => {
        const key = currentFilter === 'all' ? `${mem.user_id}` : `${team.id}-${mem.user_id}`;
        if (!seenUsers.has(key)) {
          seenUsers.set(key, {
            ...mem,
            team_id: team.id,
            team_name: team.name,
            team_color: team.color,
          });
          rawMembers.push(seenUsers.get(key));
        }
      });
    });

    const { col, dir } = this.unifiedSort;
    const sortedMembers = [...rawMembers].sort((a, b) => {
      const mult = dir === 'asc' ? 1 : -1;
      if (col === 'display_name') {
        return String(a.display_name || '').localeCompare(String(b.display_name || '')) * mult;
      }
      return ((Number(a[col]) || 0) - (Number(b[col]) || 0)) * mult;
    });

    const toggleSort = (columnKey) => {
      if (this.unifiedSort.col === columnKey) {
        this.unifiedSort.dir = this.unifiedSort.dir === 'desc' ? 'asc' : 'desc';
      } else {
        this.unifiedSort = { col: columnKey, dir: columnKey === 'display_name' ? 'asc' : 'desc' };
      }
    };

    const renderSortArrow = (columnKey) => {
      if (this.unifiedSort.col !== columnKey) {
        return <span className="ItqanStats-sortIcon ItqanStats-sortIcon--idle">↕</span>;
      }
      return (
        <span className="ItqanStats-sortIcon ItqanStats-sortIcon--active">
          {this.unifiedSort.dir === 'desc' ? '↓' : '↑'}
        </span>
      );
    };

    const selectedTeamObj = visibleTeams.find((tm) => String(tm.id) === String(currentFilter));
    const tableSubtitle =
      currentFilter === 'all' || !selectedTeamObj
        ? this.tr('teams.member_table_help_all')
        : this.tr('teams.member_table_help_team', {
            team: selectedTeamObj.name,
          });

    const forumBaseUrl = app.forum.attribute('baseUrl') || '';

    return (
      <div className="ItqanStats-card ItqanStats-card--flush">
        <div className="ItqanStats-cardBannerHeader ItqanStats-cardBannerHeader--split">
          <div>
            <h3>
              {icon('fas fa-user-check')} {this.tr('teams.member_table_title')}
              <span className="ItqanStats-countPill">{sortedMembers.length}</span>
            </h3>
            <p className="ItqanStats-cardHelp">{tableSubtitle}</p>
          </div>

          <div className="ItqanStats-tableFilterWrap">
            {icon('fas fa-filter')}
            <select
              className="FormControl ItqanStats-teamSelect"
              value={currentFilter}
              onchange={(e) => {
                this.selectedTeamFilter = e.target.value;
              }}
            >
              <option value="all">{this.tr('teams.all_teams')}</option>
              {visibleTeams.map((tm) => (
                <option key={`opt-${tm.id}`} value={String(tm.id)}>
                  {tm.name}
                </option>
              ))}
            </select>
          </div>
        </div>

        <div className="ItqanStats-tableWrap">
          <table className="ItqanStats-table">
            <thead>
              <tr>
                <th onclick={() => toggleSort('display_name')}>
                  {this.tr('teams.col_member')} {renderSortArrow('display_name')}
                </th>
                <th className="is-center" onclick={() => toggleSort('discussions')}>
                  {this.tr('teams.col_discussions')} {renderSortArrow('discussions')}
                </th>
                <th className="is-center" onclick={() => toggleSort('replies')}>
                  {this.tr('teams.col_replies')} {renderSortArrow('replies')}
                </th>
                <th className="is-center" onclick={() => toggleSort('total')}>
                  {this.tr('teams.col_total')} {renderSortArrow('total')}
                </th>
                <th className="is-center" onclick={() => toggleSort('upvotes')}>
                  <span className="ItqanStats-thUpvotes">
                    {this.tr('teams.col_upvotes')} {renderSortArrow('upvotes')}
                  </span>
                </th>
                <th className="is-center" onclick={() => toggleSort('downvotes')}>
                  <span className="ItqanStats-thDownvotes">
                    {this.tr('teams.col_downvotes')} {renderSortArrow('downvotes')}
                  </span>
                </th>
                <th />
              </tr>
            </thead>
            <tbody>
              {sortedMembers.length === 0 ? (
                <tr>
                  <td colSpan="7" className="ItqanStats-emptyCell">
                    {this.tr('teams.no_members')}
                  </td>
                </tr>
              ) : (
                sortedMembers.map((member) => (
                  <tr key={`${member.team_id}-${member.user_id}`}>
                    <td>
                      <div className="ItqanStats-memberCell">
                        <a
                          href={`${forumBaseUrl}/u/${member.username}`}
                          target="_blank"
                          rel="noopener noreferrer"
                          className="ItqanStats-memberLink"
                        >
                          <strong>{member.display_name}</strong>
                          <span className="ItqanStats-memberHandle">@{member.username}</span>
                          <span className="ItqanStats-externalIcon">
                            {icon('fas fa-external-link-alt')}
                          </span>
                        </a>
                        {currentFilter === 'all' && member.team_name && (
                          <span className="ItqanStats-memberTeamTag">{member.team_name}</span>
                        )}
                      </div>
                    </td>
                    <td className="is-center">
                      <span className="ItqanStats-numBadge ItqanStats-numBadge--emerald">
                        {formatNumber(member.discussions)}
                      </span>
                    </td>
                    <td className="is-center">
                      <span className="ItqanStats-numBadge ItqanStats-numBadge--blue">
                        {formatNumber(member.replies)}
                      </span>
                    </td>
                    <td className="is-center">
                      <strong className="ItqanStats-totalNum">{formatNumber(member.total)}</strong>
                    </td>
                    <td className="is-center">
                      <span className="ItqanStats-numBadge ItqanStats-numBadge--upvote">
                        +{formatNumber(member.upvotes || 0)}
                      </span>
                    </td>
                    <td className="is-center">
                      <span className="ItqanStats-numBadge ItqanStats-numBadge--downvote">
                        {(member.downvotes || 0) > 0 ? `-${formatNumber(member.downvotes)}` : '0'}
                      </span>
                    </td>
                    <td className="is-end">
                      <Button
                        className="Button Button--icon Button--link"
                        icon="fas fa-user-minus"
                        title={this.tr('teams.remove_member')}
                        onclick={() => this.removeMember(member.team_id, member.user_id)}
                      />
                    </td>
                  </tr>
                ))
              )}
            </tbody>
          </table>
        </div>
      </div>
    );
  }

  deleteTeam(team) {
    if (!window.confirm(this.tr('teams.confirm_delete'))) {
      return;
    }

    app
      .request({
        method: 'DELETE',
        url: `${app.forum.attribute('apiUrl')}/itqan-statistics/teams/${team.id}`,
      })
      .then(() => this.loadDashboard(false));
  }

  removeMember(teamId, userId) {
    app
      .request({
        method: 'DELETE',
        url: `${app.forum.attribute('apiUrl')}/itqan-statistics/teams/${teamId}/members/${userId}`,
      })
      .then(() => this.loadDashboard(false));
  }

  triggerRebuild() {
    this.rebuilding = true;
    m.redraw();

    app
      .request({
        method: 'POST',
        url: `${app.forum.attribute('apiUrl')}/itqan-statistics/rebuild`,
        body: { days: 365 },
      })
      .then((res) => {
        this.rebuilding = false;
        app.alerts.show(
          { type: 'success' },
          this.tr('settings.rebuild_success', {
            days: res.rebuilt_days || 365,
          })
        );
        this.loadDashboard(false);
      })
      .catch(() => {
        this.rebuilding = false;
        m.redraw();
      });
  }

  renderSettingsSection() {
    return (
      <div className="ItqanStats-dashboardStack">
        <div className="ItqanStats-card">
          <div className="ItqanStats-cardHeader">
            <h3>
              {icon('fas fa-cog')} {this.tr('settings.heading')}
            </h3>
          </div>

          <div className="Form">
            {this.buildSettingComponent({
              type: 'text',
              setting: 'itqan-statistics.timezone',
              label: this.tr('settings.timezone_label'),
              help: this.tr('settings.timezone_help'),
              placeholder: 'Asia/Riyadh',
            })}

            {this.submitButton()}
          </div>
        </div>

        <div className="ItqanStats-card">
          <div className="ItqanStats-cardHeader">
            <div>
              <h3>
                {icon('fas fa-sync-alt')} {this.tr('settings.rebuild_title')}
              </h3>
              <p className="ItqanStats-cardHelp">{this.tr('settings.rebuild_help')}</p>
            </div>
          </div>

          <Button
            className="Button Button--danger"
            icon="fas fa-bolt"
            loading={this.rebuilding}
            onclick={() => this.triggerRebuild()}
          >
            {this.tr('settings.rebuild_button')}
          </Button>
        </div>
      </div>
    );
  }
}
