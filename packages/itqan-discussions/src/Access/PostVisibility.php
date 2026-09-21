<?php

namespace Itqan\Discussions\Access;

use Flarum\Post\Post;
use Flarum\User\User;

/**
 * Remembers which posts an actor may see, and finds out for a whole page of posts in one query.
 *
 * `Post::isVisibleTo()` runs a fresh visibility-scoped query every time it is called, and the
 * `canVote` attribute calls it once per serialized discussion or post. On the discussion list that
 * is 20 identical-shaped queries (about 140 ms) per page view. The hook in `PrefetchPostVisibility`
 * asks once for the whole page before serialization; `PostPolicy::vote` then reads the answer here.
 *
 * The answer is exactly what `isVisibleTo` would give: the same `whereVisibleTo` scope, just with
 * `whereIn('id', ...)` instead of `find($id)`. A post nobody prefetched falls back to a query for
 * that single post, so behaviour never depends on the hook having run.
 *
 * Only the default "view" visibility is answered, which is all `PostPolicy::vote` needs. The query runs
 * on `Post`, not on the post's own subclass as `isVisibleTo` does, so a visibility scoper ever registered
 * on a `Post` subclass such as `CommentPost` would be skipped; today only `Post` scopers exist.
 *
 * Registered as a singleton, so it lives for one request (PHP-FPM boots the app per request). It is not
 * cleared, so it is not meant for a long-running queue worker.
 */
class PostVisibility
{
    /** @var array<int, array<int, bool>> actor id => post id => visible */
    private array $known = [];

    /** @var callable(User, int[]): iterable<int|string> ids, out of those given, the actor may see */
    private $visibleIds;

    public function __construct(?callable $visibleIds = null)
    {
        $this->visibleIds = $visibleIds
            ?? static fn (User $actor, array $ids): array => Post::whereVisibleTo($actor)->whereIn('id', $ids)->pluck('id')->all();
    }

    /**
     * @param iterable<Post> $posts
     */
    public function prefetch(User $actor, iterable $posts): void
    {
        $key = $this->key($actor);
        $ids = [];

        foreach ($posts as $post) {
            $id = (int) $post->id;

            if ($id && ! isset($this->known[$key][$id])) {
                $ids[$id] = $id;
            }
        }

        if ($ids) {
            $this->remember($actor, array_values($ids));
        }
    }

    public function isVisible(User $actor, Post $post): bool
    {
        $id = (int) $post->id;

        if (! isset($this->known[$this->key($actor)][$id])) {
            $this->remember($actor, [$id]);
        }

        return $this->known[$this->key($actor)][$id];
    }

    /**
     * @param int[] $ids
     */
    private function remember(User $actor, array $ids): void
    {
        $visible = array_flip(array_map('intval', [...($this->visibleIds)($actor, $ids)]));

        foreach ($ids as $id) {
            $this->known[$this->key($actor)][$id] = isset($visible[$id]);
        }
    }

    private function key(User $actor): int
    {
        return (int) $actor->id;
    }
}
