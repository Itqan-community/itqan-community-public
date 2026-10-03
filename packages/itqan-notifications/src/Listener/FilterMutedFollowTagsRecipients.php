<?php

namespace Itqan\Notifications\Listener;

use Flarum\Notification\Blueprint\BlueprintInterface;
use Flarum\User\User;
use Itqan\Notifications\FollowTagsChannelDecision;
use Itqan\Notifications\TagNotificationPreference;

/**
 * Drops FoF Follow Tags recipients whose effective custom channel is mute.
 *
 * Does not add recipients, does not change Follow/Lurk eligibility, and does
 * not run for any other notification blueprint (including mentions).
 */
class FilterMutedFollowTagsRecipients
{
    /**
     * @var FollowTagsChannelDecision
     */
    protected $decision;

    public function __construct(FollowTagsChannelDecision $decision)
    {
        $this->decision = $decision;
    }

    /**
     * @param User[] $recipients
     * @return User[]
     */
    public function __invoke(BlueprintInterface $blueprint, array $recipients): array
    {
        $channels = $this->decision->channelsByUser($blueprint, $recipients);

        if ($channels === null) {
            return $recipients;
        }

        $kept = [];
        $seen = [];

        foreach ($recipients as $recipient) {
            if (! $recipient instanceof User) {
                $kept[] = $recipient;

                continue;
            }

            $userId = (int) $recipient->id;

            if (isset($seen[$userId])) {
                continue;
            }

            $seen[$userId] = true;

            if (($channels[$userId] ?? null) === TagNotificationPreference::CHANNEL_MUTE) {
                continue;
            }

            $kept[] = $recipient;
        }

        return $kept;
    }
}
