# Itqan Community Statistics (`itqan/flarum-statistics`)

Hybrid community analytics dashboard for Flarum, implementing the **Flarum Community Stats Dashboard ("General Stats Page")** specification:

1. **Period Selector**:
   - Default: **Month-to-Date (`mtd`)** (1st of current month → today).
   - Presets: Last Day (24h), Last 7 Days, Last 30 Days, Last 90 Days, Last 365 Days, All Time, and Custom Date Range.
   - Displays both active window and preceding equal-length comparison window chips.
2. **Daily Activity Trend**:
   - Switchable tabs for **New Members**, **New Discussions**, and **Comment Posts**.
   - Shows period total, period-over-period `Δ%` chip (or `new` when previous = 0), and all-time total (`since community start`).
   - Renders continuous daily time-series with zero-filled gaps and previous-window overlay.
3. **Engagement Segmentation**:
   - Window-scoped classification of all community members:
     - **Returning Readers** (`joined_at < window_start` AND `last_seen_at ∈ window`)
     - **Lurkers** (`last_seen_at ∈ window` AND 0 posts AND 0 likes/votes/reactions)
     - **Active-1** (`>= 1` comment post OR `>= 1` positive reaction/upvote/like)
     - **Content Creators (With posts)** (`>= 1` comment post)
     - **Active-3** (`>= 3` comment posts)
     - **Active-10** (`>= 10` comment posts)
4. **Rolling 12-Month Charts (Decoupled Strategic View)**:
   - Always covers the last 12 calendar months regardless of the selected period filter.
   - Stacked bar chart of monthly forum volume (Discussions vs Replies).
   - Grouped bar chart of monthly member engagement segmentation.
5. **Tracked Teams & User Groups**:
   - Curated user groups stored in `itqan_stats_teams` and `itqan_stats_team_members` (independent of Flarum permission groups).
   - Per-team derived metrics: Discussions started, Replies posted (`number > 1`), Total activity, Share of forum (`%`), 12-month mini-chart (Team vs Forum), and sortable member breakdown table.
6. **Hybrid Daily Buckets & Maintenance**:
   - Persists normalized daily buckets in `itqan_stats_daily_buckets` pinned to the community timezone.
   - Includes an admin-gated **Purge & Rebuild** action and CLI command (`php flarum itqan:statistics:rebuild`).
