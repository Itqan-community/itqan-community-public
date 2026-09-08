<?php

namespace Itqan\Discussions\Access;

use Flarum\Post\Post;
use Flarum\User\Access\AbstractPolicy;
use Flarum\User\User;

class PostPolicy extends AbstractPolicy
{
    /**
     * Nobody votes on their own post. Reddit auto-upvotes the author's, which
     * makes every post start at one and so means nothing; leaving the author
     * out keeps the score a measure of what other people thought.
     */
    public function vote(User $actor, Post $post)
    {
        if ($post->user_id === $actor->id) {
            return $this->deny();
        }

        // Voting on a post the actor cannot see would leak its existence
        // through the score.
        if (! $post->isVisibleTo($actor)) {
            return $this->deny();
        }

        return $this->allow();
    }

    /**
     * Users can always edit their own comments as long as they can reply in
     * the discussion and the comment has not been hidden by a moderator.
     */
    public function edit(User $actor, Post $post)
    {
        if ($post->user_id === $actor->id && (! $post->hidden_at || $post->hidden_user_id === $actor->id) && $actor->can('reply', $post->discussion)) {
            return $this->allow();
        }
    }

    /**
     * Users can always delete (hide) their own comments even if it has nested replies.
     */
    public function hide(User $actor, Post $post)
    {
        if ($post->user_id === $actor->id && (! $post->hidden_at || $post->hidden_user_id === $actor->id) && $actor->can('reply', $post->discussion)) {
            return $this->allow();
        }
    }
}
