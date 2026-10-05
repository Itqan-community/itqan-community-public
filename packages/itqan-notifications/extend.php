<?php

use Flarum\Api\Serializer\DiscussionSerializer;
use Flarum\Api\Serializer\PostSerializer;
use Flarum\Extend;
use Flarum\Post\Event\Posted;
use Itqan\Notifications\Listener\SendReplyNotifications;
use Itqan\Notifications\Notification\CommentRepliedBlueprint;
use Itqan\Notifications\Notification\DiscussionRepliedBlueprint;
use Itqan\Notifications\Notification\DiscussionRetaggedBlueprint;
use Itqan\Notifications\Notification\DiscussionStickiedBlueprint;
use Itqan\Notifications\Notification\FilterDiscussionAuthorFromNewPost;
use Itqan\Notifications\Notification\PostApprovedBlueprint;
use Itqan\Notifications\Notification\PostRejectedBlueprint;
use Itqan\Notifications\Provider\ResolveStringsServiceProvider;

return [
    (new Extend\Frontend('forum'))
        ->js(__DIR__.'/js/dist/forum.js'),

    new Extend\Locales(__DIR__.'/locale'),

    (new Extend\User())
        ->registerPreference('dndEnabled', 'boolval', false),

    (new Extend\View)->namespace('itqan-notifications', __DIR__.'/views'),

    (new Extend\Notification)
        ->type(DiscussionRepliedBlueprint::class, PostSerializer::class, ['alert', 'email'])
        ->type(CommentRepliedBlueprint::class, PostSerializer::class, ['alert', 'email'])
        ->type(PostApprovedBlueprint::class, PostSerializer::class, ['alert', 'email'])
        ->type(PostRejectedBlueprint::class, PostSerializer::class, ['alert', 'email'])
        ->type(DiscussionStickiedBlueprint::class, DiscussionSerializer::class, ['alert', 'email'])
        ->type(DiscussionRetaggedBlueprint::class, DiscussionSerializer::class, ['alert', 'email'])
        ->beforeSending(FilterDiscussionAuthorFromNewPost::class),

    (new Extend\Event())
        ->listen(Posted::class, SendReplyNotifications::class),

    (new Extend\ServiceProvider())
        ->register(ResolveStringsServiceProvider::class),
];