<?php

namespace Mtareq\NestedReplies\Vote;

use Flarum\Discussion\Discussion;

/**
 * Recomputes a discussion's denormalised score from rows — never ±1 deltas —
 * so any interleaving of votes still converges to the truth.
 */
class DiscussionScore
{
    public static function recompute(Discussion $discussion): void
    {
        $firstPostId = (int) $discussion->first_post_id;

        $sum = $firstPostId
            ? (int) VoteCounts::forPosts([$firstPostId], new class { public $exists = false; })[$firstPostId]
            : 0;

        // Carbon (Flarum's created_at) implements DateTimeInterface, which is
        // all Ranking::hotness takes. Callers clear VoteCounts first so this
        // SUM is read fresh, not from a stale memo.
        $discussion->update([
            'votes' => $sum,
            'hotness' => Ranking::hotness($sum, $discussion->created_at),
        ]);
    }
}
