<?php

namespace Itqan\Discussions\Api;

use Flarum\Post\Post;
use Illuminate\Support\Collection;

/**
 * CreatePostController dumps every visible post ID onto discussion.posts
 * via a direct property assignment (not only setRelation). That blows up our
 * tree window and leaves LoadingPost skeletons forever.
 * Keep only the newly created post in the relationship payload.
 */
class SanitizeCreatePostPosts
{
    public function __invoke($controller, $post): void
    {
        if (! ($post instanceof Post)) {
            return;
        }

        $discussion = $post->discussion;
        if (! $discussion) {
            return;
        }

        // Match CreatePost's assignment style: `$discussion->posts = …pluck('id')`.
        // setRelation alone is not enough — the property assignment shadows it.
        $discussion->posts = new Collection([$post]);
        $discussion->setRelation('posts', new Collection([$post]));
    }
}
