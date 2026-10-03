<?php

namespace Itqan\Reactions\Access;

use Flarum\Post\Post;
use Flarum\User\Access\AbstractPolicy;
use Flarum\User\User;

class PostPolicy extends AbstractPolicy
{
    public function react(User $actor, Post $post)
    {
        if ($actor->isGuest()) {
            return $this->deny();
        }

        if ($post->hidden_at && ! $actor->can('edit', $post)) {
            return $this->deny();
        }

        return $actor->hasPermission('discussion.itqanReact') ? $this->allow() : $this->deny();
    }
}
