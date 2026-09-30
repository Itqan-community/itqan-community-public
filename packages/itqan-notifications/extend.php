<?php

use Flarum\Api\Serializer\PostSerializer;
use Flarum\Extend;
use Flarum\Post\Event\Posted;
use Itqan\Notifications\Listener\SendReplyNotifications;
use Itqan\Notifications\Notification\CommentRepliedBlueprint;
use Itqan\Notifications\Notification\DiscussionRepliedBlueprint;
use Itqan\Notifications\Notification\FilterDiscussionAuthorFromNewPost;
use Itqan\Notifications\Provider\ResolveStringsServiceProvider;

return [
    (new Extend\Frontend('forum'))
        ->js(__DIR__.'/js/dist/forum.js'),

    new Extend\Locales(__DIR__.'/locale'),

    (new Extend\User())
        ->registerPreference('dndEnabled', 'boolval', false),

    // Reply notifications (alert + email) for the discussion's author and
    // the parent comment's author. Staging's nested-reply model is
    // mtareq/flarum-nested-replies, so SendReplyNotifications resolves the
    // parent via Mtareq\NestedReplies\PostReply (the main extension used
    // posts.parent_id). FilterDiscussionAuthorFromNewPost prevents the OP
    // from also receiving the built-in subscriptions notification for
    // their own discussion.
    (new Extend\View)->namespace('itqan-notifications', __DIR__.'/views'),

    (new Extend\Notification)
        ->type(DiscussionRepliedBlueprint::class, PostSerializer::class, ['alert', 'email'])
        ->type(CommentRepliedBlueprint::class, PostSerializer::class, ['alert', 'email'])
        ->beforeSending(FilterDiscussionAuthorFromNewPost::class),

    (new Extend\Event())
        ->listen(Posted::class, SendReplyNotifications::class),

    // W1 — notification content corrections (push-title keys + linguist string fixes).
    (new Extend\ServiceProvider())
        ->register(ResolveStringsServiceProvider::class),
];