<?php

namespace Itqan\Notifications\Api;

use Flarum\Tags\Api\Serializer\TagSerializer;
use Flarum\Tags\Tag;
use Itqan\Notifications\TagNotificationPreference;

class AddTagNotificationChannelAttribute
{
    /**
     * Current-actor channel for this tag, or null when no row exists.
     *
     * Guests always get null. Another user's preference is never read:
     * queries and the preloaded relation are scoped to the serializer actor.
     *
     * @return string|null email|alert|mute|null
     */
    public function __invoke(TagSerializer $serializer, Tag $tag): ?string
    {
        $actor = $serializer->getActor();

        if (! $actor || ! $actor->exists) {
            return null;
        }

        if ($tag->relationLoaded(TagNotificationPreference::RELATION)) {
            $preference = $tag->getRelation(TagNotificationPreference::RELATION);

            return $preference instanceof TagNotificationPreference ? $preference->channel : null;
        }

        $preference = TagNotificationPreference::forActorAndTag($actor, $tag);

        return $preference ? $preference->channel : null;
    }
}
