<?php

namespace Itqan\Discussions\Api;

use Flarum\Discussion\Discussion;
use Flarum\Http\RequestUtil;
use Flarum\Post\Post;
use Illuminate\Database\Eloquent\Model;
use Itqan\Discussions\Access\PostVisibility;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Runs after a list/show controller has loaded its data and before it is serialized.
 *
 * Collects the posts about to be serialized (a discussion's first post on the list, its posts on the
 * discussion page, the posts themselves on the post list) and has `PostVisibility` check them in a
 * single query, so `canVote` no longer costs one query per row.
 *
 * Only relations that are already loaded are read. Touching an unloaded one here would lazy-load
 * it, which is the N+1 this exists to remove.
 */
class PrefetchPostVisibility
{
    public function __construct(private PostVisibility $visibility)
    {
    }

    public function __invoke($controller, $data, ServerRequestInterface $request): void
    {
        $items = $data instanceof Model ? [$data] : (is_iterable($data) ? $data : []);
        $posts = [];

        foreach ($items as $item) {
            if ($item instanceof Post) {
                $posts[] = $item;
            } elseif ($item instanceof Discussion) {
                if ($item->relationLoaded('firstPost') && $item->firstPost) {
                    $posts[] = $item->firstPost;
                }

                if ($item->relationLoaded('posts')) {
                    // ShowDiscussionController fills this with every visible post id and splices the loaded
                    // page of models over the front, so the rest of the array is bare ints.
                    foreach ($item->posts as $post) {
                        if ($post instanceof Post) {
                            $posts[] = $post;
                        }
                    }
                }
            }
        }

        $this->visibility->prefetch(RequestUtil::getActor($request), $posts);
    }
}
