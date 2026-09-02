<?php

namespace Itqan\Discussions\Listener;

use Flarum\Post\Event\Deleted;
use Flarum\Post\Post;

class UpdateReplyCountOnDelete
{
    public function handle(Deleted $event)
    {
        $post = $event->post;
        if ($post->parent_id) {
            $parent = Post::find($post->parent_id);
            if ($parent && $parent->reply_count > 0) {
                $parent->decrement('reply_count');
            }
        }
    }
}
