<?php

namespace Mtareq\NestedReplies\Access;

use Flarum\Post\Post;
use Flarum\User\Access\AbstractPolicy;
use Flarum\User\User;

class PostPolicy extends AbstractPolicy
{
    /**
     * The vote rule (enforced by VotePostController): registered actors only,
     * never on your own post, never on a post you cannot see (a vote would leak
     * its existence through the score).
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

    /**
     * In a nested replies tree, allow editing a post until someone replies directly to IT,
     * rather than locking editing when an unrelated post is added to the overall discussion.
     */
    public function edit(User $actor, Post $post)
    {
        if ($post->user_id == $actor->id && (! $post->hidden_at || $post->hidden_user_id == $actor->id) && $actor->can('reply', $post->discussion)) {
            $allowEditing = resolve('flarum.settings')->get('allow_post_editing');

            if ($allowEditing === 'reply') {
                $hasDirectReplies = Post::where('reply_to_post_id', $post->id)->exists();
                if (! $hasDirectReplies) {
                    return $this->allow();
                }
            }
        }
    }
}
