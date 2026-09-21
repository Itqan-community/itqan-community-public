<?php

namespace Itqan\Notifications\Listener;

use Flarum\Notification\NotificationSyncer;
use Flarum\Post\CommentPost;
use Flarum\Post\Event\Posted;
use Itqan\Notifications\Notification\DiscussionRepliedBlueprint;

class SendReplyNotification
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

        if (! $post instanceof CommentPost) {
            return;
        }

        if ((int) $post->number <= 1) {
            return;
        }

        $discussion = $post->discussion;

        if (! $discussion) {
            return;
        }

        $author = $discussion->user;

        if (! $author || ! $author->exists) {
            return;
        }

        if ((int) $post->user_id === (int) $author->id) {
            return;
        }

        if (! $post->isVisibleTo($author)) {
            return;
        }

        $this->notifications->sync(
            new DiscussionRepliedBlueprint($post),
            [$author]
        );
    }
}
