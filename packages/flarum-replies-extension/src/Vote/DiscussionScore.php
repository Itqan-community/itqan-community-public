<?php

namespace Mtareq\NestedReplies\Vote;

use Flarum\Discussion\Discussion;

/**
 * Recomputes a discussion's denormalised score from rows — never ±1 deltas —
 * so any interleaving of votes still converges to the truth. The score is the
 * SUM of the opening post's votes and is what the list's "Top voted" sort
 * orders by. (itqan's `hotness`/Ranking is retired and deliberately absent.)
 */
class DiscussionScore
{
    public static function recompute(Discussion $discussion): void
    {
        $firstPostId = (int) $discussion->first_post_id;

        $sum = $firstPostId
            ? (int) (VoteCounts::forPosts([$firstPostId], null)[$firstPostId] ?? 0)
            : 0;

        // Direct attribute assignment + save, never ->update([...]): core's
        // Discussion is mass-assignment guarded (fillable is empty), so a mass
        // update throws MassAssignmentException at runtime.
        $discussion->votes = $sum;
        $discussion->save();
    }
}
