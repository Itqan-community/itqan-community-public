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
 * memory.
 *
 * Priming happens in controller hooks (see extend.php):
 *   - the discussion list (ListDiscussionsController) calls primeIds() with
 *     the first-post ids (+ most_relevant_post_id for search results);
 *   - the post list (ListPostsController) — used by the reply tree — calls
 *     primeIds() with the ids it is about to serialize;
 *   - the discussion include / details page (ShowDiscussionController) calls
 *     primeOwnForDiscussion() with the discussion id.
 *
 * Once primed for that page/discussion, per-post reads are memo-only.
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
     * Prime an exact set of post ids: scores always, and the actor's own votes
     * when an actor is given. Marks every id known, so later per-post reads are
     * served from memory instead of one query each.
     *
     * @param  array<int>  $ids
     */
    public static function primeIds(array $ids, $actor = null): void
    {
        $ids = array_values(array_unique(array_map('intval', $ids)));
        if (! $ids) {
            return;
        }

        static::loadSums($ids);

        if (static::registered($actor)) {
            static::loadOwn($ids, $actor);
        }
    }

    /**
     * Batch-prime scores (and the actor's own votes when registered) for every
     * post in a whole discussion. Called from the details-page controller hook
     * — memoized per discussion, so a 60-post stream costs two queries, not 120.
     * Scores are primed for guests too, so a guest's details page never issues
     * one query per post.
     */
    public static function primeOwnForDiscussion(int $discussionId, $actor): void
    {
        if ($discussionId <= 0 || isset(static::$ownDiscussions[$discussionId])) {
            return;
        }
        static::$ownDiscussions[$discussionId] = true;

        // Every id in the discussion becomes "known" — scores always, and the
        // actor's own votes when a registered actor is present — so neither a
        // page of posts nor a guest session issues one query per post. The ids
        // come from a single indexed `discussion_id` lookup.
        $postIds = \Flarum\Post\Post::query()
            ->where('discussion_id', $discussionId)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        if (! $postIds) {
            return;
        }

        static::loadSums($postIds);

        if (static::registered($actor)) {
            static::loadOwn($postIds, $actor);
        }
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
            static::$ownDiscussions = [];
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
