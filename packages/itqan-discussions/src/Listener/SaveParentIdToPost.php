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
            if ($parentId > 0 && $parentId !== (int) $post->id) {
                $parentPost = Post::find($parentId);
                // Guard: Only nest if parent belongs to same discussion and is NOT the OP (number > 1)
                if ($parentPost && (int) $parentPost->discussion_id === (int) $post->discussion_id) {
                    if ((int) $parentPost->number > 1) {
                        $post->parent_id = $parentId;
                        
                        if (!$post->exists) {
                            $parentPost->increment('reply_count');
                        }
                    } else {
                        // Replying to OP (Post #1) is a clean top-level discussion comment
                        $post->parent_id = null;
                    }
                }
            }
        }
    }
}
