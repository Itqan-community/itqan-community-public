<?php

namespace Itqan\Notifications\Api;

use Flarum\Http\RequestUtil;
use Flarum\Tags\Tag;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Itqan\Notifications\TagNotificationPreference;
use Psr\Http\Message\ServerRequestInterface;
use Traversable;

/**
 * Bulk-load the current actor's per-tag channels onto every Tag about to be
 * serialized, so TagSerializer does not query once per tag.
 */
class LoadTagNotificationPreferences
{
    public function __invoke($controller, $data, ServerRequestInterface $request): void
    {
        $actor = RequestUtil::getActor($request);
        $tags = $this->collectTags($data);

        if ($tags->isEmpty()) {
            return;
        }

        $preferences = new Collection();

        if ($actor->exists) {
            $ids = $tags->pluck('id')->unique()->filter()->values();

            if ($ids->isNotEmpty()) {
                $preferences = TagNotificationPreference::query()
                    ->where('user_id', $actor->id)
                    ->whereIn('tag_id', $ids->all())
                    ->get()
                    ->keyBy('tag_id');
            }
        }

        foreach ($tags as $tag) {
            $tag->setRelation(
                TagNotificationPreference::RELATION,
                $preferences->get($tag->id)
            );
        }
    }

    /**
     * @param mixed $data
     * @return EloquentCollection<int, Tag>
     */
    protected function collectTags($data): EloquentCollection
    {
        $tags = new EloquentCollection();
        $this->walk($data, $tags, 0);

        return $tags;
    }

    /**
     * @param mixed $data
     */
    protected function walk($data, EloquentCollection $tags, int $depth): void
    {
        if ($depth > 6 || $data === null) {
            return;
        }

        if ($data instanceof Tag) {
            $tags->push($data);

            if ($data->relationLoaded('parent') && $data->parent instanceof Tag) {
                $this->walk($data->parent, $tags, $depth + 1);
            }

            if ($data->relationLoaded('children')) {
                $this->walk($data->children, $tags, $depth + 1);
            }

            return;
        }

        if ($data instanceof Traversable || is_array($data)) {
            foreach ($data as $item) {
                $this->walk($item, $tags, $depth + 1);
            }

            return;
        }

        if (! is_object($data) || ! method_exists($data, 'relationLoaded')) {
            return;
        }

        foreach (['tags', 'discussion', 'parent'] as $relation) {
            if ($data->relationLoaded($relation)) {
                $this->walk($data->getRelation($relation), $tags, $depth + 1);
            }
        }
    }
}
