<?php

namespace Itqan\Notifications\Listener;

use Flarum\Post\CommentPost;
use Flarum\Post\Event\Posted;
use Flarum\Notification\NotificationSyncer;
use Itqan\Notifications\Notification\CommentRepliedBlueprint;
use Mtareq\NestedReplies\PostReply;

class SendCommentRepliedNotification
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
     * When a comment is posted, check if it's a reply to another comment.
     * If so, notify the parent comment's author.
     *
     * Parent lookup reads the nested-replies link table — written by
     * StoreReplyParent in afterSave, i.e. guaranteed present before Posted
     * fires. `posts.parent_id` is inert once itqan-discussions is disabled.
     *
     * @param Posted $event
     */
    public function handle(Posted $event): void
    {
        $post = $event->post;

        // Only handle CommentPost (not first post, whispers, etc.)
        if (!($post instanceof CommentPost)) {
            return;
        }

        // First post has no reply parent.
        if ($post->number == 1) {
            return;
        }

        // Must have a poster
        if (!$post->user) {
            return;
        }

        // Look up the stored parent from the link table.
        $parentId = PostReply::query()
            ->where('post_id', $post->id)
            ->value('parent_post_id');

        if (!$parentId) {
            return;
        }

        $parentPost = \Flarum\Post\Post::find($parentId);

        if (!$parentPost || !$parentPost->user) {
            return;
        }

        // Don't notify yourself
        if ($post->user_id === $parentPost->user_id) {
            return;
        }

        // Don't notify if the parent post author can't see this reply
        if (!$post->isVisibleTo($parentPost->user)) {
            return;
        }

        // Fire the notification
        $this->notifications->sync(
            new CommentRepliedBlueprint($post, $parentPost),
            [$parentPost->user]
        );
    }
}
