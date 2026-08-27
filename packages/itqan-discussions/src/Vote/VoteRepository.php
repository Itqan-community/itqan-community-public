<?php

namespace Itqan\Discussions\Vote;

use Carbon\Carbon;
use Flarum\Post\Post;
use Flarum\User\User;
use Illuminate\Database\ConnectionInterface;

/**
 * Applies a vote and keeps the denormalised scores honest.
 *
 * The score lives in three places — the rows in post_votes, posts.votes, and
 * discussions.votes — and the whole point of the last two is that nothing
 * reads the first at query time. So every write happens in one transaction,
 * and the derived columns are recomputed from the rows rather than nudged by
 * plus or minus one: a nudge drifts the moment anything else touches the
 * table, and a recount over one post's votes is cheap.
 */
class VoteRepository
{
    protected $db;

    public function __construct(ConnectionInterface $db)
    {
        $this->db = $db;
    }

    /**
     * @param int $value one of Vote::UP, Vote::DOWN, or 0 to withdraw
     */
    public function vote(Post $post, User $actor, int $value): Post
    {
        $this->db->transaction(function () use ($post, $actor, $value) {
            $votes = $this->db->table('post_votes')
                ->where('post_id', $post->id)
                ->where('user_id', $actor->id);

            if ($value === 0) {
                $votes->delete();
            } else {
                // Voting the other way is one statement, not a delete and an
                // insert: the pair is the primary key, so the database
                // resolves it.
                $this->db->table('post_votes')->updateOrInsert(
                    ['post_id' => $post->id, 'user_id' => $actor->id],
                    ['value' => $value, 'created_at' => Carbon::now()]
                );
            }

            $this->refreshPost($post);
        });

        return $post->refresh();
    }

    /**
     * Recomputes a post's score, and the discussion's if this is the post the
     * discussion is ranked by.
     */
    public function refreshPost(Post $post): void
    {
        $score = (int) $this->db->table('post_votes')
            ->where('post_id', $post->id)
            ->sum('value');

        $this->db->table('posts')->where('id', $post->id)->update(['votes' => $score]);

        $discussion = $post->discussion;

        // A discussion is ranked by its opening post, as agreed on #21. A vote
        // on a reply moves that reply within the thread and nothing else.
        if (! $discussion || $post->number !== 1) {
            return;
        }

        $this->db->table('discussions')->where('id', $discussion->id)->update([
            'votes' => $score,
            'hotness' => Ranking::hotness($score, $discussion->created_at),
        ]);
    }
}
