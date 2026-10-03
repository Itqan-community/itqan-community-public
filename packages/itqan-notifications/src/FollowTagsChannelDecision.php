<?php

namespace Itqan\Notifications;

use Flarum\Discussion\Discussion;
use Flarum\Notification\Blueprint\BlueprintInterface;
use Flarum\User\User;
use FoF\FollowTags\Notifications\NewDiscussionBlueprint;
use FoF\FollowTags\Notifications\NewDiscussionTagBlueprint;
use FoF\FollowTags\Notifications\NewPostBlueprint;

/**
 * Resolves the effective custom channel for FoF Follow Tags blueprints only.
 *
 * Does not decide recipient eligibility. Non-Follow-Tags blueprints and
 * unresolvable discussions fail open (no override).
 */
class FollowTagsChannelDecision
{
    /**
     * @var EffectiveChannelResolver
     */
    protected $resolver;

    public function __construct(EffectiveChannelResolver $resolver)
    {
        $this->resolver = $resolver;
    }

    public function isFollowTagsBlueprint(BlueprintInterface $blueprint): bool
    {
        return $blueprint instanceof NewDiscussionBlueprint
            || $blueprint instanceof NewPostBlueprint
            || $blueprint instanceof NewDiscussionTagBlueprint;
    }

    /**
     * Effective channel for one user, or null when there is no Follow Tags
     * override (including non-Follow-Tags blueprints).
     */
    public function effectiveChannel(User $user, BlueprintInterface $blueprint): ?string
    {
        $map = $this->channelsByUser($blueprint, [$user]);

        if ($map === null) {
            return null;
        }

        return $map[(int) $user->id] ?? null;
    }

    /**
     * Bulk effective channels for a recipient list.
     *
     * @param User[] $users
     * @return array<int, string|null>|null user_id => channel; null = fail open
     */
    public function channelsByUser(BlueprintInterface $blueprint, array $users): ?array
    {
        if (! $this->isFollowTagsBlueprint($blueprint)) {
            return null;
        }

        $discussion = FollowTagsDiscussion::fromBlueprint($blueprint);

        if (! $discussion instanceof Discussion) {
            return null;
        }

        $tagIds = $this->tagIds($discussion);

        if ($tagIds === []) {
            return null;
        }

        $userIds = [];

        foreach ($users as $user) {
            if ($user instanceof User) {
                $userIds[] = (int) $user->id;
            }
        }

        if ($userIds === []) {
            return [];
        }

        $loaded = $this->resolver->loadForUsersAndTags($userIds, $tagIds);
        $map = [];

        foreach ($userIds as $userId) {
            $map[$userId] = $this->resolver->resolve($loaded[$userId] ?? [], $tagIds);
        }

        return $map;
    }

    /**
     * Drop Follow Tags recipients that must not receive this driver.
     *
     * @param User[] $users
     * @return User[]
     */
    public function filterForDriver(BlueprintInterface $blueprint, array $users, string $driverName): array
    {
        $channels = $this->channelsByUser($blueprint, $users);

        if ($channels === null) {
            return $users;
        }

        $kept = [];

        foreach ($users as $user) {
            if (! $user instanceof User) {
                $kept[] = $user;

                continue;
            }

            $channel = $channels[(int) $user->id] ?? null;

            if ($this->allowsDriver($channel, $driverName)) {
                $kept[] = $user;
            }
        }

        return $kept;
    }

    public function allowsDriver(?string $channel, string $driverName): bool
    {
        if ($channel === TagNotificationPreference::CHANNEL_MUTE) {
            return false;
        }

        if ($channel === TagNotificationPreference::CHANNEL_ALERT) {
            return $driverName !== 'email' && $driverName !== 'push';
        }

        return true;
    }

    /**
     * @return int[]
     */
    protected function tagIds(Discussion $discussion): array
    {
        if (! method_exists($discussion, 'tags')) {
            return [];
        }

        $tags = $discussion->tags;

        if (! $tags || $tags->isEmpty()) {
            return [];
        }

        return $tags->pluck('id')->map(function ($id) {
            return (int) $id;
        })->unique()->values()->all();
    }
}
