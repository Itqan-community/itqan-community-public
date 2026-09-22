<?php

namespace Mtareq\NestedReplies\Listener;

use Flarum\Post\Event\Saving;
use Flarum\Post\Post;
use Illuminate\Support\Arr;
use Mtareq\NestedReplies\PostReply;

class StoreReplyParent
{
    public function handle(Saving $event): void
    {
        $parentId = Arr::get($event->data, 'attributes.replyToPostId');

        if (! $parentId || ! is_numeric($parentId)) {
            return;
        }

        $event->post->afterSave(function (Post $post) use ($parentId) {
            $parent = Post::query()->find($parentId);

            if (
                ! $parent
                || (int) $parent->id === (int) $post->id
                || (int) $parent->discussion_id !== (int) $post->discussion_id
            ) {
                return;
            }

            PostReply::query()->updateOrCreate(
                ['post_id' => $post->id],
                ['parent_post_id' => $parent->id]
            );
        });
    }
}
