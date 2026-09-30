<?php

namespace Itqan\Notifications\Notification;

use Flarum\Notification\Blueprint\BlueprintInterface;
use Flarum\Subscriptions\Notification\NewPostBlueprint;

/**
 * Stops the discussion's author from receiving the built-in NewPostBlueprint
 * (subscriptions) for their own discussion, since DiscussionRepliedBlueprint
 * now covers them via alert + email and we don't want duplicates.
 */
class FilterDiscussionAuthorFromNewPost
{
    /**
     * @param \Flarum\User\User[] $recipients
     * @return \Flarum\User\User[]
     */
    public function __invoke(BlueprintInterface $blueprint, array $recipients): array
    {
        if (! $blueprint instanceof NewPostBlueprint) {
            return $recipients;
        }

        $discussion = $blueprint->post->discussion;
        $authorId = $discussion ? $discussion->user_id : null;

        if (! $authorId) {
            return $recipients;
        }

        $filtered = [];

        foreach ($recipients as $recipient) {
            if ((int) $recipient->id !== (int) $authorId) {
                $filtered[] = $recipient;
            }
        }

        return $filtered;
    }
}