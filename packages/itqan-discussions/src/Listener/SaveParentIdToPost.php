<?php

namespace Itqan\Discussions\Listener;

use Flarum\Post\Event\Saving;
use Flarum\Post\Post;
use Illuminate\Support\Arr;

class SaveParentIdToPost
{
    public function handle(Saving $event)
    {
        $post = $event->post;
        $data = $event->data;

        $parentId = Arr::get($data, 'attributes.parentId') 
            ?? Arr::get($data, 'attributes.parent_id') 
            ?? Arr::get($data, 'attributes.replyToId')
            ?? Arr::get($data, 'parentId')
            ?? Arr::get($data, 'parent_id');

        // Fallback: If no explicit parentId, extract from <POSTMENTION id="...">
        if ($parentId === null) {
            $content = $post->parsedContent ?? $post->content ?? '';
            if (preg_match('/<POSTMENTION\s+[^>]*id="(\d+)"/i', $content, $matches)) {
                $parentId = (int) $matches[1];
            }
        }

        if ($parentId !== null) {
            $parentId = (int) $parentId;
            if ($parentId > 0) {
                $parentPost = Post::find($parentId);
                if ($parentPost && (int) $parentPost->discussion_id === (int) $post->discussion_id) {
                    $post->parent_id = $parentId;
                    
                    if (!$post->exists) {
                        $parentPost->increment('reply_count');
                    }
                }
            }
        }
    }
}
