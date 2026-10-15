<?php

namespace Itqan\Statistics\Service;

use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Flarum\Settings\SettingsRepositoryInterface;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Schema\Builder as SchemaBuilder;
use Itqan\Statistics\Model\DailyBucket;
use Itqan\Statistics\Model\Team;

class StatisticsService
{
    public const CACHE_PREFIX = 'itqan-statistics.';
    public const CACHE_VERSION_KEY = 'itqan-statistics.cache_version';

    protected ConnectionInterface $db;
    protected SchemaBuilder $schema;
    protected CacheRepository $cache;
    protected SettingsRepositoryInterface $settings;

    public function __construct(
        ConnectionInterface $db,
        CacheRepository $cache,
        SettingsRepositoryInterface $settings
    ) {
        $this->db = $db;
        $this->schema = $db->getSchemaBuilder();
        $this->cache = $cache;
        $this->settings = $settings;
    }

    public function getCommunityTimezone(): string
    {
        $tz = (string) $this->settings->get('itqan-statistics.timezone', 'Asia/Riyadh');
        try {
            new \DateTimeZone($tz);
            return $tz;
        } catch (\Throwable $e) {
            return 'Asia/Riyadh';
        }
    }

    public function getCacheVersion(): int
    {
        return (int) $this->cache->get(self::CACHE_VERSION_KEY, 1);
    }

    public function bumpCacheVersion(): int
    {
        $next = $this->getCacheVersion() + 1;
        $this->cache->forever(self::CACHE_VERSION_KEY, $next);
        return $next;
    }

    /**
     * Resolves a period preset or custom date range into [startCarbon, endCarbon, presetName, label]
     * in the community timezone.
     */
    public function resolvePeriod(?string $period, ?string $customFrom = null, ?string $customTo = null): array
    {
        $tz = $this->getCommunityTimezone();
        $now = Carbon::now($tz);
        $todayEnd = $now->copy()->endOfDay();

        $period = $period ?: 'mtd';

        switch ($period) {
            case 'mtd': // Month-to-date: 1st of current month to today
                $start = $now->copy()->startOfMonth()->startOfDay();
                $end = $todayEnd;
                break;

            case 'last_day':
            case '1':
                $start = $now->copy()->subDays(1)->startOfDay();
                $end = $todayEnd;
                break;

            case 'last_7_days':
            case '7':
                $start = $now->copy()->subDays(6)->startOfDay();
                $end = $todayEnd;
                break;

            case 'last_30_days':
            case '30':
                $start = $now->copy()->subDays(29)->startOfDay();
                $end = $todayEnd;
                break;

            case 'last_90_days':
            case '90':
                $start = $now->copy()->subDays(89)->startOfDay();
                $end = $todayEnd;
                break;

            case 'last_365_days':
            case '365':
                $start = $now->copy()->subDays(364)->startOfDay();
                $end = $todayEnd;
                break;

            case 'all_time':
            case 'all':
                $earliest = $this->getEarliestCommunityDate($tz);
                $start = $earliest->copy()->startOfDay();
                $end = $todayEnd;
                break;

            case 'custom':
                if ($customFrom && $customTo) {
                    try {
                        $start = Carbon::parse($customFrom, $tz)->startOfDay();
                        $end = Carbon::parse($customTo, $tz)->endOfDay();
                        if ($end->lt($start)) {
                            [$start, $end] = [$end->copy()->startOfDay(), $start->copy()->endOfDay()];
                        }
                    } catch (\Throwable $e) {
                        $start = $now->copy()->startOfMonth()->startOfDay();
                        $end = $todayEnd;
                    }
                } else {
                    $start = $now->copy()->startOfMonth()->startOfDay();
                    $end = $todayEnd;
                }
                break;

            default:
                $period = 'mtd';
                $start = $now->copy()->startOfMonth()->startOfDay();
                $end = $todayEnd;
                break;
        }

        // Number of calendar days in window (inclusive)
        $days = max(1, (int) $start->copy()->startOfDay()->diffInDays($end->copy()->startOfDay()) + 1);

        // Preceding equal-length comparison window: [start - L days, start - 1 second]
        $prevEnd = $start->copy()->subSecond();
        $prevStart = $start->copy()->subDays($days)->startOfDay();

        return [
            'period' => $period,
            'timezone' => $tz,
            'start' => $start,
            'end' => $end,
            'days' => $days,
            'from_date' => $start->format('Y-m-d'),
            'to_date' => $end->format('Y-m-d'),
            'prev_start' => $prevStart,
            'prev_end' => $prevEnd,
            'prev_from_date' => $prevStart->format('Y-m-d'),
            'prev_to_date' => $prevEnd->format('Y-m-d'),
        ];
    }

    protected function getEarliestCommunityDate(string $tz): Carbon
    {
        $minUser = $this->db->table('users')->min('joined_at');
        $minDisc = $this->db->table('discussions')->min('created_at');

        $candidates = array_filter([$minUser, $minDisc]);
        if (empty($candidates)) {
            return Carbon::now($tz)->subDays(30);
        }

        $earliestUtc = min($candidates);
        return Carbon::parse($earliestUtc, 'UTC')->setTimezone($tz);
    }

    /**
     * Ensures daily buckets exist in `itqan_stats_daily_buckets` for every date in [$fromDate, $toDate].
     * Past-day buckets are immutable once computed; today's bucket is refreshed if older than 5 minutes.
     */
    public function ensureDailyBuckets(string $fromDate, string $toDate, bool $forceRebuild = false): array
    {
        $tz = $this->getCommunityTimezone();
        $todayStr = Carbon::now($tz)->format('Y-m-d');

        $start = Carbon::createFromFormat('Y-m-d', $fromDate, $tz)->startOfDay();
        $end = Carbon::createFromFormat('Y-m-d', $toDate, $tz)->startOfDay();

        // Cap bucket generation to at most 730 days to avoid runaway queries
        if ($start->diffInDays($end) > 730) {
            $start = $end->copy()->subDays(730);
            $fromDate = $start->format('Y-m-d');
        }

        $existing = DailyBucket::query()
            ->where('bucket_date', '>=', $fromDate)
            ->where('bucket_date', '<=', $toDate)
            ->get()
            ->keyBy('bucket_date');

        $missingDates = [];
        $period = CarbonPeriod::create($start, '1 day', $end);

        foreach ($period as $dateObj) {
            $d = $dateObj->format('Y-m-d');
            if ($forceRebuild || ! $existing->has($d)) {
                $missingDates[] = $d;
            } elseif ($d === $todayStr) {
                $bucket = $existing->get($d);
                $computedAt = Carbon::parse($bucket->computed_at, 'UTC');
                if ($computedAt->diffInMinutes(Carbon::now('UTC')) >= 5) {
                    $missingDates[] = $d;
                }
            } else {
                // Also recompute if existing bucket was created before upvotes/downvotes support was added
                $bucket = $existing->get($d);
                $payload = $bucket->getDecodedPayload();
                if (! array_key_exists('upvotes', $payload)) {
                    $missingDates[] = $d;
                }
            }
        }

        if (! empty($missingDates)) {
            $this->computeAndStoreBucketsRange(min($missingDates), max($missingDates), $missingDates, $tz);
            $existing = DailyBucket::query()
                ->where('bucket_date', '>=', $fromDate)
                ->where('bucket_date', '<=', $toDate)
                ->orderBy('bucket_date', 'asc')
                ->get()
                ->keyBy('bucket_date');
        }

        $result = [];
        foreach ($period as $dateObj) {
            $d = $dateObj->format('Y-m-d');
            if ($existing->has($d)) {
                $b = $existing->get($d);
                $payload = $b->getDecodedPayload();
                $result[$d] = [
                    'date' => $d,
                    'users' => (int) $b->new_users_count,
                    'discussions' => (int) $b->discussions_count,
                    'posts' => (int) $b->posts_count,
                    'replies' => (int) $b->replies_count,
                    'upvotes' => (int) ($payload['upvotes'] ?? 0),
                    'downvotes' => (int) ($payload['downvotes'] ?? 0),
                    'payload' => $payload,
                ];
            } else {
                $result[$d] = [
                    'date' => $d,
                    'users' => 0,
                    'discussions' => 0,
                    'posts' => 0,
                    'replies' => 0,
                    'upvotes' => 0,
                    'downvotes' => 0,
                    'payload' => [],
                ];
            }
        }

        return $result;
    }

    /**
     * Batch-computes daily buckets for [$minDate, $maxDate] in community timezone and upserts them.
     */
    protected function computeAndStoreBucketsRange(string $minDate, string $maxDate, array $targetDates, string $tz): void
    {
        $rangeStartLocal = Carbon::createFromFormat('Y-m-d', $minDate, $tz)->startOfDay();
        $rangeEndLocal = Carbon::createFromFormat('Y-m-d', $maxDate, $tz)->endOfDay();

        $utcStart = $rangeStartLocal->copy()->setTimezone('UTC')->format('Y-m-d H:i:s');
        $utcEnd = $rangeEndLocal->copy()->setTimezone('UTC')->format('Y-m-d H:i:s');

        $targetSet = array_flip($targetDates);
        $buckets = [];
        foreach ($targetDates as $d) {
            $buckets[$d] = [
                'users' => 0,
                'discussions' => 0,
                'posts' => 0,
                'replies' => 0,
                'upvotes' => 0,
                'downvotes' => 0,
                'user_discussions' => [],
                'user_replies' => [],
                'user_posts' => [],
                'user_upvotes_received' => [],
                'user_downvotes_received' => [],
                'liker_ids' => [],
            ];
        }

        // 1. Users joined
        $users = $this->db->table('users')
            ->select(['id', 'joined_at'])
            ->where('joined_at', '>=', $utcStart)
            ->where('joined_at', '<=', $utcEnd)
            ->get();

        foreach ($users as $u) {
            if (! $u->joined_at) {
                continue;
            }
            $d = Carbon::parse($u->joined_at, 'UTC')->setTimezone($tz)->format('Y-m-d');
            if (isset($targetSet[$d])) {
                $buckets[$d]['users']++;
            }
        }

        // 2. Discussions started
        $discussions = $this->db->table('discussions')
            ->select(['id', 'user_id', 'created_at'])
            ->where('created_at', '>=', $utcStart)
            ->where('created_at', '<=', $utcEnd)
            ->get();

        foreach ($discussions as $disc) {
            if (! $disc->created_at) {
                continue;
            }
            $d = Carbon::parse($disc->created_at, 'UTC')->setTimezone($tz)->format('Y-m-d');
            if (isset($targetSet[$d])) {
                $buckets[$d]['discussions']++;
                if ($disc->user_id) {
                    $uid = (int) $disc->user_id;
                    $buckets[$d]['user_discussions'][$uid] = ($buckets[$d]['user_discussions'][$uid] ?? 0) + 1;
                }
            }
        }

        // 3. Posts (type = 'comment', openers included; replies have number > 1)
        $posts = $this->db->table('posts')
            ->select(['id', 'user_id', 'number', 'created_at'])
            ->where('type', 'comment')
            ->where('created_at', '>=', $utcStart)
            ->where('created_at', '<=', $utcEnd)
            ->get();

        foreach ($posts as $post) {
            if (! $post->created_at) {
                continue;
            }
            $d = Carbon::parse($post->created_at, 'UTC')->setTimezone($tz)->format('Y-m-d');
            if (isset($targetSet[$d])) {
                $buckets[$d]['posts']++;
                $uid = $post->user_id ? (int) $post->user_id : null;
                if ($uid) {
                    $buckets[$d]['user_posts'][$uid] = ($buckets[$d]['user_posts'][$uid] ?? 0) + 1;
                }
                if ((int) $post->number > 1) {
                    $buckets[$d]['replies']++;
                    if ($uid) {
                        $buckets[$d]['user_replies'][$uid] = ($buckets[$d]['user_replies'][$uid] ?? 0) + 1;
                    }
                }
            }
        }

        // 4. Positive reactions / likes + Upvotes & Downvotes per day & received by post authors
        $reactionStats = $this->collectDailyReactionsAndVotes($utcStart, $utcEnd, $tz);
        foreach ($reactionStats as $d => $dayInfo) {
            if (isset($targetSet[$d])) {
                $buckets[$d]['liker_ids'] = array_values(array_map('intval', array_keys($dayInfo['likers'] ?? [])));
                $buckets[$d]['upvotes'] = (int) ($dayInfo['upvotes'] ?? 0);
                $buckets[$d]['downvotes'] = (int) ($dayInfo['downvotes'] ?? 0);
                $buckets[$d]['user_upvotes_received'] = $dayInfo['user_upvotes_received'] ?? [];
                $buckets[$d]['user_downvotes_received'] = $dayInfo['user_downvotes_received'] ?? [];
            }
        }

        $nowUtc = Carbon::now('UTC')->format('Y-m-d H:i:s');
        $rowsToUpsert = [];

        foreach ($buckets as $d => $data) {
            $rowsToUpsert[] = [
                'bucket_date' => $d,
                'new_users_count' => $data['users'],
                'discussions_count' => $data['discussions'],
                'posts_count' => $data['posts'],
                'replies_count' => $data['replies'],
                'payload' => json_encode([
                    'upvotes' => $data['upvotes'],
                    'downvotes' => $data['downvotes'],
                    'user_discussions' => $data['user_discussions'],
                    'user_replies' => $data['user_replies'],
                    'user_posts' => $data['user_posts'],
                    'user_upvotes_received' => $data['user_upvotes_received'],
                    'user_downvotes_received' => $data['user_downvotes_received'],
                    'liker_ids' => $data['liker_ids'],
                ]),
                'computed_at' => $nowUtc,
            ];
        }

        foreach (array_chunk($rowsToUpsert, 100) as $chunk) {
            $this->db->table('itqan_stats_daily_buckets')->upsert(
                $chunk,
                ['bucket_date'],
                ['new_users_count', 'discussions_count', 'posts_count', 'replies_count', 'payload', 'computed_at']
            );
        }
    }

    /**
     * Collects per-day:
     *  - `likers`: distinct user IDs who liked/upvoted/reacted
     *  - `upvotes`: count of positive votes/likes cast on that day
     *  - `downvotes`: count of negative votes cast on that day
     *  - `user_upvotes_received`: [author_user_id => count]
     *  - `user_downvotes_received`: [author_user_id => count]
     */
    protected function collectDailyReactionsAndVotes(string $utcStart, string $utcEnd, string $tz): array
    {
        $byDay = [];

        $ensureDay = function (string $d) use (&$byDay) {
            if (! isset($byDay[$d])) {
                $byDay[$d] = [
                    'likers' => [],
                    'upvotes' => 0,
                    'downvotes' => 0,
                    'user_upvotes_received' => [],
                    'user_downvotes_received' => [],
                ];
            }
        };

        // A. post_votes (fof/gamification)
        if ($this->schema->hasTable('post_votes')) {
            $votes = $this->db->table('post_votes as pv')
                ->join('posts as p', 'p.id', '=', 'pv.post_id')
                ->select([
                    'pv.user_id as voter_id',
                    'p.user_id as author_id',
                    'pv.value',
                    $this->db->raw('COALESCE(pv.created_at, p.created_at) as effective_at'),
                ])
                ->whereRaw('COALESCE(pv.created_at, p.created_at) >= ?', [$utcStart])
                ->whereRaw('COALESCE(pv.created_at, p.created_at) <= ?', [$utcEnd])
                ->get();

            foreach ($votes as $v) {
                if (! $v->effective_at) {
                    continue;
                }
                $d = Carbon::parse($v->effective_at, 'UTC')->setTimezone($tz)->format('Y-m-d');
                $ensureDay($d);

                $val = (int) $v->value;
                $authorId = $v->author_id ? (int) $v->author_id : null;

                if ($val > 0) {
                    $byDay[$d]['upvotes'] += $val;
                    if ($v->voter_id) {
                        $byDay[$d]['likers'][(int) $v->voter_id] = true;
                    }
                    if ($authorId) {
                        $byDay[$d]['user_upvotes_received'][$authorId] = ($byDay[$d]['user_upvotes_received'][$authorId] ?? 0) + $val;
                    }
                } elseif ($val < 0) {
                    $abs = abs($val);
                    $byDay[$d]['downvotes'] += $abs;
                    if ($authorId) {
                        $byDay[$d]['user_downvotes_received'][$authorId] = ($byDay[$d]['user_downvotes_received'][$authorId] ?? 0) + $abs;
                    }
                }
            }
        }

        // B. post_likes (flarum/likes)
        if ($this->schema->hasTable('post_likes')) {
            $hasCreatedAt = $this->schema->hasColumn('post_likes', 'created_at');
            $dateExpr = $hasCreatedAt ? 'COALESCE(pl.created_at, p.created_at)' : 'p.created_at';

            $likes = $this->db->table('post_likes as pl')
                ->join('posts as p', 'p.id', '=', 'pl.post_id')
                ->select([
                    'pl.user_id as liker_id',
                    'p.user_id as author_id',
                    $this->db->raw("$dateExpr as effective_at"),
                ])
                ->whereRaw("$dateExpr >= ?", [$utcStart])
                ->whereRaw("$dateExpr <= ?", [$utcEnd])
                ->get();

            foreach ($likes as $l) {
                if (! $l->effective_at) {
                    continue;
                }
                $d = Carbon::parse($l->effective_at, 'UTC')->setTimezone($tz)->format('Y-m-d');
                $ensureDay($d);

                if ($l->liker_id) {
                    $byDay[$d]['likers'][(int) $l->liker_id] = true;
                }
                // If post_votes is not used, count post_likes toward upvotes as well
                if (! $this->schema->hasTable('post_votes')) {
                    $byDay[$d]['upvotes']++;
                    if ($l->author_id) {
                        $aid = (int) $l->author_id;
                        $byDay[$d]['user_upvotes_received'][$aid] = ($byDay[$d]['user_upvotes_received'][$aid] ?? 0) + 1;
                    }
                }
            }
        }

        // C. itqan_post_reactions
        if ($this->schema->hasTable('itqan_post_reactions')) {
            $reactions = $this->db->table('itqan_post_reactions as pr')
                ->join('posts as p', 'p.id', '=', 'pr.post_id')
                ->select([
                    'pr.user_id',
                    $this->db->raw('COALESCE(pr.created_at, p.created_at) as effective_at'),
                ])
                ->whereRaw('COALESCE(pr.created_at, p.created_at) >= ?', [$utcStart])
                ->whereRaw('COALESCE(pr.created_at, p.created_at) <= ?', [$utcEnd])
                ->get();

            foreach ($reactions as $r) {
                if (! $r->user_id || ! $r->effective_at) {
                    continue;
                }
                $d = Carbon::parse($r->effective_at, 'UTC')->setTimezone($tz)->format('Y-m-d');
                $ensureDay($d);
                $byDay[$d]['likers'][(int) $r->user_id] = true;
            }
        }

        return $byDay;
    }

    /**
     * Lifetime ("الإجمالي منذ بداية المجتمع" / "Total since community start") totals.
     */
    public function getLifetimeTotals(): array
    {
        $version = $this->getCacheVersion();
        $cacheKey = self::CACHE_PREFIX . "lifetime.v{$version}";

        return $this->cache->remember($cacheKey, 300, function () {
            $users = (int) $this->db->table('users')->count();
            $discussions = (int) $this->db->table('discussions')->count();
            $posts = (int) $this->db->table('posts')->where('type', 'comment')->count();
            $replies = (int) $this->db->table('posts')->where('type', 'comment')->where('number', '>', 1)->count();

            $upvotes = 0;
            $downvotes = 0;
            if ($this->schema->hasTable('post_votes')) {
                $upvotes = (int) $this->db->table('post_votes')->where('value', '>', 0)->sum('value');
                $downvotes = (int) abs((int) $this->db->table('post_votes')->where('value', '<', 0)->sum('value'));
            } elseif ($this->schema->hasTable('post_likes')) {
                $upvotes = (int) $this->db->table('post_likes')->count();
            }

            return [
                'users' => $users,
                'discussions' => $discussions,
                'posts' => $posts,
                'replies' => $replies,
                'upvotes' => $upvotes,
                'downvotes' => $downvotes,
            ];
        });
    }

    /**
     * Computes delta percentage and status:
     * Δ% = round((current - previous) / previous * 100)
     * previous = 0 && current > 0 -> "new"
     */
    public function computeDelta(int $current, int $previous): array
    {
        if ($previous === 0) {
            if ($current > 0) {
                return [
                    'current' => $current,
                    'previous' => $previous,
                    'delta_pct' => null,
                    'status' => 'new',
                ];
            }
            return [
                'current' => 0,
                'previous' => 0,
                'delta_pct' => 0,
                'status' => 'flat',
            ];
        }

        $pct = (int) round((($current - $previous) / $previous) * 100);
        return [
            'current' => $current,
            'previous' => $previous,
            'delta_pct' => $pct,
            'status' => $pct > 0 ? 'up' : ($pct < 0 ? 'down' : 'flat'),
        ];
    }

    /**
     * Engagement levels for a given window.
     */
    public function computeEngagementForWindow(Carbon $startLocal, Carbon $endLocal, ?array $dailyBuckets = null): array
    {
        $utcStart = $startLocal->copy()->setTimezone('UTC')->format('Y-m-d H:i:s');
        $utcEnd = $endLocal->copy()->setTimezone('UTC')->format('Y-m-d H:i:s');

        // 1. Posters & content item counts
        $userPostCounts = [];
        if ($dailyBuckets !== null) {
            foreach ($dailyBuckets as $bucket) {
                $up = $bucket['payload']['user_posts'] ?? [];
                foreach ($up as $uid => $cnt) {
                    $uid = (int) $uid;
                    $userPostCounts[$uid] = ($userPostCounts[$uid] ?? 0) + (int) $cnt;
                }
            }
        } else {
            $rows = $this->db->table('posts')
                ->select(['user_id', $this->db->raw('COUNT(*) as items')])
                ->where('type', 'comment')
                ->whereNotNull('user_id')
                ->where('created_at', '>=', $utcStart)
                ->where('created_at', '<=', $utcEnd)
                ->groupBy('user_id')
                ->get();

            foreach ($rows as $r) {
                $userPostCounts[(int) $r->user_id] = (int) $r->items;
            }
        }

        // 2. Likers set
        $likerSet = [];
        if ($dailyBuckets !== null) {
            foreach ($dailyBuckets as $bucket) {
                $lids = $bucket['payload']['liker_ids'] ?? [];
                foreach ($lids as $uid) {
                    $likerSet[(int) $uid] = true;
                }
            }
        } else {
            $dailyReactions = $this->collectDailyReactionsAndVotes($utcStart, $utcEnd, $startLocal->timezoneName);
            foreach ($dailyReactions as $dayInfo) {
                foreach (($dayInfo['likers'] ?? []) as $uid => $_) {
                    $likerSet[(int) $uid] = true;
                }
            }
        }

        // 3. Logins set (users with last_seen_at in [utcStart, utcEnd])
        $seenUsers = $this->db->table('users')
            ->select(['id', 'joined_at', 'last_seen_at'])
            ->where('last_seen_at', '>=', $utcStart)
            ->where('last_seen_at', '<=', $utcEnd)
            ->get();

        $returningReaders = 0;
        $lurkers = 0;

        foreach ($seenUsers as $u) {
            $uid = (int) $u->id;
            if ($u->joined_at && $u->joined_at < $utcStart) {
                $returningReaders++;
            }
            $hasPosts = isset($userPostCounts[$uid]) && $userPostCounts[$uid] > 0;
            $hasLikes = isset($likerSet[$uid]);
            if (! $hasPosts && ! $hasLikes) {
                $lurkers++;
            }
        }

        // Active-1: >= 1 content item OR >= 1 like
        $active1Users = $likerSet;
        foreach ($userPostCounts as $uid => $count) {
            if ($count >= 1) {
                $active1Users[$uid] = true;
            }
        }

        $withPosts = 0;
        $active3 = 0;
        $active10 = 0;

        foreach ($userPostCounts as $uid => $count) {
            if ($count >= 1) {
                $withPosts++;
            }
            if ($count >= 3) {
                $active3++;
            }
            if ($count >= 10) {
                $active10++;
            }
        }

        return [
            'lurkers' => $lurkers,
            'returning_readers' => $returningReaders,
            'active_1' => count($active1Users),
            'with_posts' => $withPosts,
            'active_3' => $active3,
            'active_10' => $active10,
        ];
    }

    /**
     * Rolling 12 calendar months charts (decoupled from period filter).
     */
    public function getRollingTwelveMonthsData(): array
    {
        $version = $this->getCacheVersion();
        $tz = $this->getCommunityTimezone();
        $now = Carbon::now($tz);
        $currentMonthKey = $now->format('Y-m');
        $cacheKey = self::CACHE_PREFIX . "rolling_12m.v{$version}.{$currentMonthKey}";

        return $this->cache->remember($cacheKey, 600, function () use ($now) {
            $firstMonthStart = $now->copy()->startOfMonth()->subMonths(11)->startOfDay();
            $lastMonthEnd = $now->copy()->endOfDay();

            $allBuckets = $this->ensureDailyBuckets(
                $firstMonthStart->format('Y-m-d'),
                $lastMonthEnd->format('Y-m-d')
            );

            $months = [];
            for ($i = 11; $i >= 0; $i--) {
                $mStart = $now->copy()->startOfMonth()->subMonths($i)->startOfDay();
                $mEnd = ($i === 0) ? $now->copy()->endOfDay() : $mStart->copy()->endOfMonth()->endOfDay();
                $ym = $mStart->format('Y-m');

                $monthBuckets = [];
                $discussions = 0;
                $replies = 0;
                $posts = 0;
                $newUsers = 0;
                $userDiscussions = [];
                $userReplies = [];

                foreach ($allBuckets as $dateStr => $b) {
                    if (substr($dateStr, 0, 7) === $ym) {
                        $monthBuckets[$dateStr] = $b;
                        $discussions += $b['discussions'];
                        $replies += $b['replies'];
                        $posts += $b['posts'];
                        $newUsers += $b['users'];

                        foreach (($b['payload']['user_discussions'] ?? []) as $uid => $cnt) {
                            $userDiscussions[(int) $uid] = ($userDiscussions[(int) $uid] ?? 0) + (int) $cnt;
                        }
                        foreach (($b['payload']['user_replies'] ?? []) as $uid => $cnt) {
                            $userReplies[(int) $uid] = ($userReplies[(int) $uid] ?? 0) + (int) $cnt;
                        }
                    }
                }

                $engagement = $this->computeEngagementForWindow($mStart, $mEnd, $monthBuckets);

                $months[] = [
                    'month' => $ym,
                    'label_en' => $mStart->locale('en')->isoFormat('MMM YYYY'),
                    'label_ar' => $mStart->locale('ar')->isoFormat('MMM YYYY'),
                    'volume' => [
                        'discussions' => $discussions,
                        'replies' => $replies,
                        'total' => $discussions + $replies,
                        'posts' => $posts,
                        'new_users' => $newUsers,
                    ],
                    'engagement' => $engagement,
                    'user_discussions' => $userDiscussions,
                    'user_replies' => $userReplies,
                ];
            }

            return $months;
        });
    }

    /**
     * Teams section metrics for the selected window + 3 separate 12-month mini-bar chart series per team
     * (Discussions, Replies, Total Activity) + member upvotes/downvotes.
     */
    public function getTeamsMetrics(
        array $currentBuckets,
        int $forumDiscussionsWindow,
        int $forumRepliesWindow,
        array $rollingTwelveMonths,
        bool $includeHidden = false
    ): array {
        $query = Team::query()->with(['members.user'])->orderBy('sort_order', 'asc')->orderBy('id', 'asc');
        if (! $includeHidden) {
            $query->where('is_visible', true);
        }

        $teams = $query->get();

        // Aggregate per-user discussions, replies, upvotes received, and downvotes received across the selected window buckets
        $windowUserDiscussions = [];
        $windowUserReplies = [];
        $windowUserUpvotes = [];
        $windowUserDownvotes = [];

        foreach ($currentBuckets as $b) {
            foreach (($b['payload']['user_discussions'] ?? []) as $uid => $cnt) {
                $windowUserDiscussions[(int) $uid] = ($windowUserDiscussions[(int) $uid] ?? 0) + (int) $cnt;
            }
            foreach (($b['payload']['user_replies'] ?? []) as $uid => $cnt) {
                $windowUserReplies[(int) $uid] = ($windowUserReplies[(int) $uid] ?? 0) + (int) $cnt;
            }
            foreach (($b['payload']['user_upvotes_received'] ?? []) as $uid => $cnt) {
                $windowUserUpvotes[(int) $uid] = ($windowUserUpvotes[(int) $uid] ?? 0) + (int) $cnt;
            }
            foreach (($b['payload']['user_downvotes_received'] ?? []) as $uid => $cnt) {
                $windowUserDownvotes[(int) $uid] = ($windowUserDownvotes[(int) $uid] ?? 0) + (int) $cnt;
            }
        }

        $forumTotalWindow = $forumDiscussionsWindow + $forumRepliesWindow;
        $result = [];

        foreach ($teams as $team) {
            $teamDiscussions = 0;
            $teamReplies = 0;
            $teamUpvotes = 0;
            $teamDownvotes = 0;
            $memberRows = [];
            $memberIds = [];

            foreach ($team->members as $member) {
                $uid = (int) $member->user_id;
                $memberIds[] = $uid;
                $userModel = $member->user;

                $uDisc = $windowUserDiscussions[$uid] ?? 0;
                $uRep = $windowUserReplies[$uid] ?? 0;
                $uTotal = $uDisc + $uRep;
                $uUp = $windowUserUpvotes[$uid] ?? 0;
                $uDown = $windowUserDownvotes[$uid] ?? 0;

                $teamDiscussions += $uDisc;
                $teamReplies += $uRep;
                $teamUpvotes += $uUp;
                $teamDownvotes += $uDown;

                $username = $userModel ? $userModel->username : ('user_' . $uid);
                $displayName = $member->display_name ?: ($userModel ? ($userModel->display_name ?? $userModel->username) : $username);

                $memberRows[] = [
                    'id' => (int) $member->id,
                    'user_id' => $uid,
                    'team_id' => (int) $team->id,
                    'team_name' => $team->name,
                    'team_color' => $team->color ?: '#0d9488',
                    'username' => $username,
                    'display_name' => $displayName,
                    'avatar_url' => $userModel ? $userModel->avatar_url : null,
                    'role' => $member->role,
                    'last_seen_at' => $userModel && $userModel->last_seen_at ? Carbon::parse($userModel->last_seen_at)->toIso8601String() : null,
                    'joined_at' => $userModel && $userModel->joined_at ? Carbon::parse($userModel->joined_at)->toIso8601String() : null,
                    'discussions' => $uDisc,
                    'replies' => $uRep,
                    'total' => $uTotal,
                    'upvotes' => $uUp,
                    'downvotes' => $uDown,
                ];
            }

            // Default sort members by total activity desc
            usort($memberRows, fn ($a, $b) => $b['total'] <=> $a['total'] ?: strcmp($a['display_name'], $b['display_name']));

            $teamTotal = $teamDiscussions + $teamReplies;

            // Compute 12-month mini-chart series (Team vs Forum for Discussions, Replies, Total)
            $monthlySeries = [];
            foreach ($rollingTwelveMonths as $m) {
                $mTeamDisc = 0;
                $mTeamRep = 0;
                foreach ($memberIds as $uid) {
                    $mTeamDisc += $m['user_discussions'][$uid] ?? 0;
                    $mTeamRep += $m['user_replies'][$uid] ?? 0;
                }
                $monthlySeries[] = [
                    'month' => $m['month'],
                    'label_en' => $m['label_en'],
                    'label_ar' => $m['label_ar'],
                    'team_discussions' => $mTeamDisc,
                    'forum_discussions' => $m['volume']['discussions'],
                    'team_replies' => $mTeamRep,
                    'forum_replies' => $m['volume']['replies'],
                    'team_total' => $mTeamDisc + $mTeamRep,
                    'forum_total' => $m['volume']['total'],
                ];
            }

            $result[] = [
                'id' => (int) $team->id,
                'name' => $team->name,
                'description' => $team->description,
                'color' => $team->color ?: '#0d9488',
                'is_visible' => (bool) $team->is_visible,
                'sort_order' => (int) $team->sort_order,
                'member_count' => count($memberRows),
                'metrics' => [
                    'discussions' => $teamDiscussions,
                    'replies' => $teamReplies,
                    'total' => $teamTotal,
                    'upvotes' => $teamUpvotes,
                    'downvotes' => $teamDownvotes,
                    'forum_discussions' => $forumDiscussionsWindow,
                    'forum_replies' => $forumRepliesWindow,
                    'forum_total' => $forumTotalWindow,
                    'share_total_pct' => $forumTotalWindow > 0 ? round(($teamTotal / $forumTotalWindow) * 100, 1) : 0.0,
                    'share_discussions_pct' => $forumDiscussionsWindow > 0 ? round(($teamDiscussions / $forumDiscussionsWindow) * 100, 1) : 0.0,
                    'share_replies_pct' => $forumRepliesWindow > 0 ? round(($teamReplies / $forumRepliesWindow) * 100, 1) : 0.0,
                ],
                'members' => $memberRows,
                'monthly_trend' => $monthlySeries,
            ];
        }

        return $result;
    }

    /**
     * Full dashboard payload combining all sections.
     */
    public function getDashboardPayload(
        ?string $period = 'mtd',
        ?string $customFrom = null,
        ?string $customTo = null,
        bool $forceRefresh = false
    ): array {
        if ($forceRefresh) {
            $this->bumpCacheVersion();
        }

        $resolved = $this->resolvePeriod($period, $customFrom, $customTo);

        // 1. Current window buckets
        $currentBuckets = $this->ensureDailyBuckets($resolved['from_date'], $resolved['to_date'], $forceRefresh);

        // 2. Previous equal-length window buckets for period-over-period delta (up to 365 days)
        $includePrevious = $resolved['period'] !== 'all_time' && $resolved['days'] <= 366;
        $previousBuckets = $includePrevious
            ? $this->ensureDailyBuckets($resolved['prev_from_date'], $resolved['prev_to_date'], $forceRefresh)
            : [];

        // Sum period totals
        $currUsers = 0;
        $currDiscussions = 0;
        $currPosts = 0;
        $currReplies = 0;
        $currUpvotes = 0;
        $currDownvotes = 0;
        $dailySeries = [];

        foreach ($currentBuckets as $d => $b) {
            $currUsers += $b['users'];
            $currDiscussions += $b['discussions'];
            $currPosts += $b['posts'];
            $currReplies += $b['replies'];
            $currUpvotes += $b['upvotes'];
            $currDownvotes += $b['downvotes'];

            $dailySeries[] = [
                'date' => $d,
                'users' => $b['users'],
                'discussions' => $b['discussions'],
                'posts' => $b['posts'],
                'replies' => $b['replies'],
                'upvotes' => $b['upvotes'],
                'downvotes' => $b['downvotes'],
            ];
        }

        $prevUsers = 0;
        $prevDiscussions = 0;
        $prevPosts = 0;
        $prevReplies = 0;
        $prevUpvotes = 0;
        $prevDownvotes = 0;
        $prevSeries = [];

        foreach ($previousBuckets as $d => $b) {
            $prevUsers += $b['users'];
            $prevDiscussions += $b['discussions'];
            $prevPosts += $b['posts'];
            $prevReplies += $b['replies'];
            $prevUpvotes += $b['upvotes'];
            $prevDownvotes += $b['downvotes'];

            $prevSeries[] = [
                'date' => $d,
                'users' => $b['users'],
                'discussions' => $b['discussions'],
                'posts' => $b['posts'],
                'replies' => $b['replies'],
                'upvotes' => $b['upvotes'],
                'downvotes' => $b['downvotes'],
            ];
        }

        $lifetime = $this->getLifetimeTotals();
        $engagement = $this->computeEngagementForWindow($resolved['start'], $resolved['end'], $currentBuckets);
        $rolling12m = $this->getRollingTwelveMonthsData();

        $teams = $this->getTeamsMetrics(
            $currentBuckets,
            $currDiscussions,
            $currReplies,
            $rolling12m,
            true // Return all teams with is_visible flag so admin can toggle & filter
        );

        // Strip internal user maps from rolling12m before sending over wire
        $cleanRolling12m = array_map(function ($m) {
            return [
                'month' => $m['month'],
                'label_en' => $m['label_en'],
                'label_ar' => $m['label_ar'],
                'volume' => $m['volume'],
                'engagement' => $m['engagement'],
            ];
        }, $rolling12m);

        return [
            'window' => [
                'period' => $resolved['period'],
                'timezone' => $resolved['timezone'],
                'from' => $resolved['from_date'],
                'to' => $resolved['to_date'],
                'days' => $resolved['days'],
                'comparison_enabled' => $includePrevious,
                'prev_from' => $includePrevious ? $resolved['prev_from_date'] : null,
                'prev_to' => $includePrevious ? $resolved['prev_to_date'] : null,
            ],
            'lifetime' => $lifetime,
            'period_summary' => [
                'users' => $includePrevious ? $this->computeDelta($currUsers, $prevUsers) : ['current' => $currUsers, 'previous' => null, 'delta_pct' => null, 'status' => 'none'],
                'discussions' => $includePrevious ? $this->computeDelta($currDiscussions, $prevDiscussions) : ['current' => $currDiscussions, 'previous' => null, 'delta_pct' => null, 'status' => 'none'],
                'posts' => $includePrevious ? $this->computeDelta($currPosts, $prevPosts) : ['current' => $currPosts, 'previous' => null, 'delta_pct' => null, 'status' => 'none'],
                'replies' => $includePrevious ? $this->computeDelta($currReplies, $prevReplies) : ['current' => $currReplies, 'previous' => null, 'delta_pct' => null, 'status' => 'none'],
                'upvotes' => $includePrevious ? $this->computeDelta($currUpvotes, $prevUpvotes) : ['current' => $currUpvotes, 'previous' => null, 'delta_pct' => null, 'status' => 'none'],
                'downvotes' => $includePrevious ? $this->computeDelta($currDownvotes, $prevDownvotes) : ['current' => $currDownvotes, 'previous' => null, 'delta_pct' => null, 'status' => 'none'],
            ],
            'daily_trend' => [
                'current' => $dailySeries,
                'previous' => $prevSeries,
            ],
            'engagement' => $engagement,
            'rolling_12_months' => $cleanRolling12m,
            'teams' => $teams,
        ];
    }

    /**
     * Purge & Rebuild action (admin-gated).
     */
    public function purgeAndRebuild(int $daysToRebuild = 365): array
    {
        $tz = $this->getCommunityTimezone();
        $deletedCount = $this->db->table('itqan_stats_daily_buckets')->count();
        $this->db->table('itqan_stats_daily_buckets')->truncate();

        $this->bumpCacheVersion();

        $end = Carbon::now($tz)->startOfDay();
        $start = $end->copy()->subDays(max(1, $daysToRebuild - 1));

        $rebuilt = $this->ensureDailyBuckets($start->format('Y-m-d'), $end->format('Y-m-d'), true);

        return [
            'purged_buckets' => $deletedCount,
            'rebuilt_days' => count($rebuilt),
            'from' => $start->format('Y-m-d'),
            'to' => $end->format('Y-m-d'),
            'timezone' => $tz,
        ];
    }
}
