<?php

namespace Itqan\Notifications\Listener;

use Flarum\Approval\Event\PostWasApproved;
use Flarum\Notification\NotificationSyncer;
use Flarum\Sticky\Event\DiscussionWasStickied;
use Flarum\Tags\Event\DiscussionWasTagged;
use Itqan\Notifications\Notification\DiscussionRetaggedBlueprint;
use Itqan\Notifications\Notification\DiscussionStickiedBlueprint;
use Itqan\Notifications\Notification\PostApprovedBlueprint;

/**
 * Moderation notifications for the custom blueprints.
 *
 * Each handler notifies the content author (never the acting moderator) and
 * deliberately no-ops when the underlying model/user is missing, so a deleted
 * author or a system action can never trigger a fatal.
 *
 * The event classes for the optional extensions (approval/sticky/tags) are only
 * resolved on dispatch; if an extension is not installed its event simply never
 * fires, so this class stays safe on minimal installs.
 */
class SendModerationNotifications
{
    /**
     * @var NotificationSyncer
     */
    protected $notifications;

    public function __construct(NotificationSyncer $notifications)
    {
        $this->notifications = $notifications;
    }

    /**
     * A pending post was approved and is now published -> tell its author.
     */
    public function whenPostWasApproved(PostWasApproved $event): void
    {
        $post = $event->post;
        $author = $post->user;

        if (! $author || ! $author->exists) {
            return;
        }

        // Never notify a moderator about their own action.
        if ($event->actor && (int) $event->actor->id === (int) $author->id) {
            return;
        }

        $this->notifications->sync(new PostApprovedBlueprint($post), [$author]);
    }

    /**
     * A discussion was pinned to the top of its category -> tell its author.
     */
    public function whenDiscussionWasStickied(DiscussionWasStickied $event): void
    {
        $discussion = $event->discussion;
        $author = $discussion->user;

        if (! $author || ! $author->exists) {
            return;
        }

        if ($event->user && (int) $event->user->id === (int) $author->id) {
            return;
        }

        $this->notifications->sync(new DiscussionStickiedBlueprint($discussion, $event->user), [$author]);
    }

    /**
     * A discussion's tags changed -> tell its author which tag it now sits in.
     *
     * `DiscussionWasTagged` is raised before the pivot is synced, so the actual
     * new tag names are read straight after save; we diff against the old tags
     * the event carries and fall back to the discussion's full tag list when
     * tags were only removed.
     */
    public function whenDiscussionWasTagged(DiscussionWasTagged $event): void
    {
        $discussion = $event->discussion;
        $actor = $event->actor;

        $oldIds = array_map(static function ($tag) {
            return (int) $tag->id;
        }, $event->oldTags);

        // At this point the discussion already exposes its new tags (this is the
        // same source core's own DiscussionTaggedPost uses), so diff against the
        // old tags the event carries and fall back to the full list when tags
        // were only removed.
        $newTags = $discussion->tags()->get();
        $added = $newTags->filter(static function ($tag) use ($oldIds) {
            return ! in_array((int) $tag->id, $oldIds, true);
        });

        $chosen = $added->isNotEmpty() ? $added : $newTags;
        $tagName = $chosen->pluck('name')->implode('، ');

        $author = $discussion->user;

        if (! $author || ! $author->exists) {
            return;
        }

        if ($actor && (int) $actor->id === (int) $author->id) {
            return;
        }

        $this->notifications->sync(new DiscussionRetaggedBlueprint($discussion, $actor, $tagName), [$author]);
    }
}
