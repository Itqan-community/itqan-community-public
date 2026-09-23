<?php

namespace Mtareq\NestedReplies\Access;

use Flarum\Post\Post;
use Flarum\User\Access\AbstractPolicy;
use Flarum\User\User;

class PostPolicy extends AbstractPolicy
{
    /**
     * The vote rule (serialized as `canVote`, enforced by VotePostController):
     * registered actors only, never on your own post, never on a post you
     * cannot see (a vote would leak its existence through the score).
     */
    public function vote(User $actor, Post $post)
    {
        if (! $actor->exists) {
            return $this->deny();
        }

        if ($post->user_id === $actor->id) {
            return $this->deny();
        }

        if (! $post->isVisibleTo($actor)) {
            return $this->deny();
        }

        return $this->allow();
    }
}
