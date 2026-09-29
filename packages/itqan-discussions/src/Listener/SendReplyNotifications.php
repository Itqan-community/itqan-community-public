<?php

namespace Itqan\Discussions\Listener;

use Flarum\Notification\NotificationSyncer;
use Flarum\Post\CommentPost;
use Flarum\Post\Event\Posted;
use Itqan\Discussions\Notification\CommentRepliedBlueprint;
use Itqan\Discussions\Notification\DiscussionRepliedBlueprint;

/**
 * On every new posted comment, fire two notifications:
 *
 *  - DiscussionRepliedBlueprint → the discussion's author (unless self-reply
 *    or the post is not visible to the author). Unconditional of subscription
 *    state, so the OP no longer misses notifications just because they aren't
 *    following their own discussion.
 *
 *  - CommentRepliedBlueprint → the parent comment's author when the post is
 *    a nested reply (parent_id set) and the author is not the replier and
 *    can see the reply. Skipped when the reply is to the OP itself (parent_id
 *    is null in that case thanks to SaveParentIdToPost).
 */
class SendReplyNotifications
{
    /**
     * @var NotificationSyncer
     */
    protected $notifications;

    public function __construct(NotificationSyncer $notifications)
    {
        $this->notifications = $notifications;
    }

    public function handle(Posted $event)
    {
        $post = $event->post;

        // Only handle comment posts (excludes the opening post, edits, etc.).
        if (! $post instanceof CommentPost) {
            return;
        }

        // Only replies (post #2 and beyond). Post #1 is the discussion OP and
        // is created via a different event chain.
        if ((int) $post->number <= 1) {
            return;
        }

        $discussion = $post->discussion;

        if (! $discussion) {
            return;
        }

        // 1) Notify the discussion author (if it exists, isn't the replier,
        //    and is allowed to see this reply).
        $author = $discussion->user;

        if ($author && $author->exists && (int) $post->user_id !== (int) $author->id) {
            if ($post->isVisibleTo($author)) {
                $this->notifications->sync(
                    new DiscussionRepliedBlueprint($post),
                    [$author]
                );
            }
        }

        // 2) Notify the parent comment's author, if this is a nested reply
        //    and the parent has a different author who can see the reply.
        $parentId = $post->parent_id;

        if (! $parentId) {
            return;
        }

        $parent = $post->parent;

        if (! $parent || ! $parent->user) {
            return;
        }

        if ((int) $parent->user_id === (int) $post->user_id) {
            return;
        }

        if (! $post->isVisibleTo($parent->user)) {
            return;
        }

        $this->notifications->sync(
            new CommentRepliedBlueprint($post, $parent),
            [$parent->user]
        );
    }
}