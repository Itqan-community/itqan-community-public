<?php

namespace Itqan\Notifications;

use Flarum\Discussion\Discussion;
use Flarum\Notification\Blueprint\BlueprintInterface;
use Flarum\Post\Post;
use FoF\FollowTags\Notifications\NewDiscussionBlueprint;
use FoF\FollowTags\Notifications\NewDiscussionTagBlueprint;
use FoF\FollowTags\Notifications\NewPostBlueprint;

/**
 * Maps FoF Follow Tags blueprints to a Discussion without assuming getSubject().
 */
class FollowTagsDiscussion
{
    public static function fromBlueprint(BlueprintInterface $blueprint): ?Discussion
    {
        if ($blueprint instanceof NewDiscussionBlueprint || $blueprint instanceof NewDiscussionTagBlueprint) {
            return $blueprint->discussion instanceof Discussion ? $blueprint->discussion : null;
        }

        if ($blueprint instanceof NewPostBlueprint) {
            $post = $blueprint->post;

            if (! $post instanceof Post) {
                return null;
            }

            $discussion = $post->discussion;

            return $discussion instanceof Discussion ? $discussion : null;
        }

        return null;
    }
}
