<?php

use Carbon\Carbon;
use Illuminate\Database\Schema\Builder;
use Itqan\Discussions\Vote\Ranking;

/*
 * Carries existing likes over as upvotes.
 *
 * Voting replaces flarum/likes on this forum. A like already means "this was
 * useful", which is what an upvote means, so dropping the table would throw
 * away a year of the community's reading of its own threads and start every
 * score at zero.
 *
 * Safe to run more than once: `insertOrIgnore` collides with the (post, user)
 * primary key on a second pass, and the scores are recomputed from the rows
 * rather than added to.
 *
 * There is no `down`. Once likes and votes share a table nothing distinguishes
 * a converted like from a vote cast afterwards, so reversing this would delete
 * real votes. Rolling back the table itself is the earlier migration's job.
 */
return [
    'up' => function (Builder $schema) {
        $db = $schema->getConnection();

        // The extension may not be installed at all, in which case there is
        // nothing to carry over.
        if (! $schema->hasTable('post_likes')) {
            return;
        }

        $now = Carbon::now();

        $db->table('post_likes')->orderBy('post_id')->chunk(500, function ($likes) use ($db, $now) {
            $rows = [];

            foreach ($likes as $like) {
                $rows[] = [
                    'post_id' => $like->post_id,
                    'user_id' => $like->user_id,
                    'value' => 1,
                    // post_likes only gained created_at in 2021, so older rows
                    // have none.
                    'created_at' => $like->created_at ?? $now,
                ];
            }

            if ($rows) {
                $db->table('post_votes')->insertOrIgnore($rows);
            }
        });

        // Recompute every score that now has rows behind it, rather than
        // trusting an incremental count.
        $db->table('posts')
            ->whereIn('id', function ($query) {
                $query->select('post_id')->from('post_votes');
            })
            ->update([
                'votes' => $db->raw('(SELECT COALESCE(SUM(value), 0) FROM post_votes WHERE post_votes.post_id = posts.id)'),
            ]);

        // And the discussions those posts open.
        $db->table('discussions')
            ->update([
                'votes' => $db->raw('COALESCE((SELECT p.votes FROM posts p WHERE p.discussion_id = discussions.id AND p.number = 1 LIMIT 1), 0)'),
            ]);

        foreach ($db->table('discussions')->select('id', 'votes', 'created_at')->cursor() as $discussion) {
            $db->table('discussions')->where('id', $discussion->id)->update([
                'hotness' => Ranking::hotness(
                    (int) $discussion->votes,
                    $discussion->created_at ? Carbon::parse($discussion->created_at) : null
                ),
            ]);
        }
    },

    'down' => function (Builder $schema) {
        // Deliberately empty; see the note above.
    },
];
