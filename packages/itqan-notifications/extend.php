<?php

use Flarum\Api\Serializer\PostSerializer;
use Flarum\Extend;
use Flarum\Post\Event\Posted;
use Itqan\Notifications\Listener\SendReplyNotification;
use Itqan\Notifications\Notification\DiscussionRepliedBlueprint;
use Itqan\Notifications\Notification\FilterDiscussionAuthorFromNewPost;

return [
    (new Extend\Frontend('forum'))
        ->js(__DIR__.'/js/dist/forum.js'),

    new Extend\Locales(__DIR__.'/locale'),

    (new Extend\User())
        ->registerPreference('dndEnabled', 'boolval', false),

    (new Extend\View)
        ->namespace('itqan-notifications', __DIR__.'/views'),

    (new Extend\Notification())
        ->type(DiscussionRepliedBlueprint::class, PostSerializer::class, ['alert', 'email'])
        ->beforeSending(FilterDiscussionAuthorFromNewPost::class),

    (new Extend\Event())
        ->listen(Posted::class, SendReplyNotification::class),
];
