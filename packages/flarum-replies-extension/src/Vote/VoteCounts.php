<?php

namespace Mtareq\NestedReplies\Vote;

use Mtareq\NestedReplies\PostVote;

/**
 * Request-scoped batched loader for post votes.
 *
 * We deliberately keep votes in their own table (no `posts.votes` column), so
 * serialization must never issue one query per post. This loader memoizes
 * per-request: a first call fetches every *unknown* id in a single grouped
 * query, later calls — including the same post reached again — are served from
 * memory. `prime()` lets a controller batch the scores it is about to
 * serialize; `primeOwnForDiscussion()` batches the actor's own votes for a
 * whole discussion stream.
 *
 * Scores and own votes memoize independently (`$knownSum` vs `$knownOwn`):
 * score reads never trigger an actor-scoped query, so actor-independent
 * priming cannot poison the own-vote memo — and vice versa.
 *
 * PHP-FPM resets statics between requests, so the memo needs no explicit
 * lifecycle beyond `clear()`, which exists for tests and long-lived runtimes.
 */
class VoteCounts
{
    /** @var array<int, int> post id => SUM(value) */
    protected static $sums = [];

    /** @var array<int, string|null> post id => 'up' | 'down' (actor's own vote) */
    protected static $userVotes = [];

    /** @var int|null actor id the userVotes memo was built for; null = none */
    protected static $forUser;

    /** @var array<int, true> score ids fetched (including "known to have zero votes") */
    protected static $knownSum = [];

    /** @var array<int, true> own-vote ids fetched for $forUser (including "known to have no vote") */
    protected static $knownOwn = [];

    /** @var array<int, true> discussion ids already batch-primed for own votes */
    protected static $ownDiscussions = [];

    public static function clear(): void
    {
        static::$sums = [];
        static::$userVotes = [];
        static::$forUser = null;
        static::$knownSum = [];
        static::$knownOwn = [];
        static::$ownDiscussions = [];
    }

    /**
     * Scores for every requested id — actor-independent, so guests and list
     * pages pay exactly one query for any number of unknown ids.
     *
     * Never fetches own votes (the two memos are independent by contract).
     *
     * @param  array<int>  $ids
     * @return array<int, int> every requested id => sum (0 when absent)
     */
    public static function forPosts(array $ids, $actor): array
    {
        $ids = array_values(array_unique(array_map('intval', $ids)));
        static::loadSums($ids);

        $out = [];
        foreach ($ids as $id) {
            $out[$id] = (int) (static::$sums[$id] ?? 0);
        }

        return $out;
    }

    /**
     * The actor's own vote per post: 'up' | 'down' | null.
     *
     * @param  array<int>  $ids
     * @return array<int, string|null>
     */
    public static function userVotes(array $ids, $actor): array
    {
        $ids = array_values(array_unique(array_map('intval', $ids)));

        $registered = static::registered($actor);
        if (! $registered) {
            $out = [];
            foreach ($ids as $id) {
                $out[$id] = null;
            }

            return $out;
        }

        static::loadSums($ids);      // scores may not be memoized yet either
        static::loadOwn($ids, $actor);

        $out = [];
        foreach ($ids as $id) {
            $out[$id] = static::$userVotes[$id] ?? null;
        }

        return $out;
    }

    /**
     * Batch-prime scores before serialization (controller hook — no actor
     * needed: scores are actor-independent).
     *
     * @param  array<int>  $ids
     */
    public static function prime(array $ids): void
    {
        static::loadSums(array_values(array_unique(array_map('intval', $ids))));
    }

    /**
     * Batch-prime the actor's own votes for a whole discussion in one query.
     * Called from PostSerializer's attribute closure — memoized per discussion,
     * so a 60-post stream costs one own-vote query, not sixty.
     */
    public static function primeOwnForDiscussion(int $discussionId, $actor): void
    {
        if (! static::registered($actor) || $discussionId <= 0) {
            return;
        }
        if (isset(static::$ownDiscussions[$discussionId])) {
            return;
        }
        static::$ownDiscussions[$discussionId] = true;

        $rows = PostVote::query()
            ->where('user_id', $actor->id)
            ->whereIn('post_id', function ($query) use ($discussionId) {
                $query->select('id')->from('posts')->where('discussion_id', $discussionId);
            })
            ->get(['post_id', 'value']);

        foreach ($rows as $row) {
            $id = (int) $row->post_id;
            static::$knownOwn[$id] = true;
            static::$userVotes[$id] = $row->value >= 0 ? 'up' : 'down';
        }
        static::$forUser = (int) $actor->id;
    }

    protected static function registered($actor): bool
    {
        return is_object($actor)
            && (! isset($actor->exists) || $actor->exists)
            && isset($actor->id)
            && $actor->id;
    }

    /**
     * Fetch scores for ids not yet memoized — one grouped query.
     *
     * Marks every missing id known *before* querying, so ids with no votes at
     * all are cached as "sum 0" instead of being refetched on every call.
     *
     * @param  array<int>  $ids
     */
    protected static function loadSums(array $ids): void
    {
        $missing = array_values(array_filter($ids, fn ($id) => ! isset(static::$knownSum[$id])));
        if (! $missing) {
            return;
        }

        foreach ($missing as $id) {
            static::$knownSum[$id] = true;
        }

        $rows = PostVote::query()
            ->whereIn('post_id', $missing)
            ->groupBy('post_id')
            ->selectRaw('post_id, COALESCE(SUM(value), 0) as total')
            ->get();

        foreach ($rows as $row) {
            static::$sums[(int) $row->post_id] = (int) $row->total;
        }
    }

    /**
     * Fetch the registered actor's own votes for ids not yet memoized.
     *
     * @param  array<int>  $ids
     */
    protected static function loadOwn(array $ids, $actor): void
    {
        if (static::$forUser !== null && static::$forUser !== (int) $actor->id) {
            static::$userVotes = [];
            static::$knownOwn = [];
        }
        static::$forUser = (int) $actor->id;

        $missing = array_values(array_filter($ids, fn ($id) => ! isset(static::$knownOwn[$id])));
        if (! $missing) {
            return;
        }

        foreach ($missing as $id) {
            static::$knownOwn[$id] = true;
        }

        $rows = PostVote::query()
            ->whereIn('post_id', $missing)
            ->where('user_id', $actor->id)
            ->get(['post_id', 'value']);

        foreach ($rows as $row) {
            static::$userVotes[(int) $row->post_id] = $row->value >= 0 ? 'up' : 'down';
        }
    }
}
