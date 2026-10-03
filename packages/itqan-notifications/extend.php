<?php

use Flarum\Api\Controller\CreateDiscussionController;
use Flarum\Api\Controller\CreatePostController;
use Flarum\Api\Controller\ListDiscussionsController;
use Flarum\Api\Controller\ListPostsController;
use Flarum\Api\Controller\ShowDiscussionController;
use Flarum\Api\Controller\ShowForumController;
use Flarum\Api\Controller\ShowPostController;
use Flarum\Api\Serializer\PostSerializer;
use Flarum\Extend;
use Flarum\Flags\Api\Controller\ListFlagsController;
use Flarum\Post\Event\Posted;
use Flarum\Tags\Api\Controller\ListTagsController;
use Flarum\Tags\Api\Controller\ShowTagController;
use Flarum\Tags\Api\Serializer\TagSerializer;
use Itqan\Notifications\Api\AddTagNotificationChannelAttribute;
use Itqan\Notifications\Api\DeleteTagNotificationChannelController;
use Itqan\Notifications\Api\LoadTagNotificationPreferences;
use Itqan\Notifications\Api\SetTagNotificationChannelController;
use Itqan\Notifications\Listener\FilterMutedFollowTagsRecipients;
use Itqan\Notifications\Listener\SendReplyNotifications;
use Itqan\Notifications\Notification\CommentRepliedBlueprint;
use Itqan\Notifications\Notification\DiscussionRepliedBlueprint;
use Itqan\Notifications\Notification\FilterDiscussionAuthorFromNewPost;
use Itqan\Notifications\Provider\NotificationDriverProvider;
use Itqan\Notifications\Provider\ResolveStringsServiceProvider;

return [
    (new Extend\Frontend('forum'))
        ->js(__DIR__.'/js/dist/forum.js')
        ->css(__DIR__.'/less/forum.less'),

    new Extend\Locales(__DIR__.'/locale'),

    (new Extend\User())
        ->registerPreference('dndEnabled', 'boolval', false),

    (new Extend\Routes('api'))
        ->post('/tags/{id}/notification-channel', 'itqan-notifications.tag-notification-channel', SetTagNotificationChannelController::class)
        ->delete('/tags/{id}/notification-channel', 'itqan-notifications.tag-notification-channel.delete', DeleteTagNotificationChannelController::class),

    (new Extend\ApiSerializer(TagSerializer::class))
        ->attribute('itqanNotificationChannel', AddTagNotificationChannelAttribute::class),

    (new Extend\ApiController(ListTagsController::class))
        ->prepareDataForSerialization(LoadTagNotificationPreferences::class),

    (new Extend\ApiController(ShowTagController::class))
        ->prepareDataForSerialization(LoadTagNotificationPreferences::class),

    (new Extend\ApiController(ShowForumController::class))
        ->prepareDataForSerialization(LoadTagNotificationPreferences::class),

    (new Extend\ApiController(ListDiscussionsController::class))
        ->prepareDataForSerialization(LoadTagNotificationPreferences::class),

    (new Extend\ApiController(ShowDiscussionController::class))
        ->prepareDataForSerialization(LoadTagNotificationPreferences::class),

    (new Extend\ApiController(CreateDiscussionController::class))
        ->prepareDataForSerialization(LoadTagNotificationPreferences::class),

    (new Extend\ApiController(ListPostsController::class))
        ->prepareDataForSerialization(LoadTagNotificationPreferences::class),

    (new Extend\ApiController(ShowPostController::class))
        ->prepareDataForSerialization(LoadTagNotificationPreferences::class),

    (new Extend\ApiController(CreatePostController::class))
        ->prepareDataForSerialization(LoadTagNotificationPreferences::class),

    (new Extend\Conditional())
        ->whenExtensionEnabled('flarum-flags', fn () => [
            (new Extend\ApiController(ListFlagsController::class))
                ->prepareDataForSerialization(LoadTagNotificationPreferences::class),
        ]),

    (new Extend\Conditional())
        ->whenExtensionEnabled('fof-follow-tags', fn () => [
            (new Extend\Notification())
                ->beforeSending(FilterMutedFollowTagsRecipients::class),
            (new Extend\ServiceProvider())
                ->register(NotificationDriverProvider::class),
        ]),

    // Reply notifications landed on staging after this branch was opened.
    // Staging's nested-reply model is mtareq/flarum-nested-replies, so
    // SendReplyNotifications resolves the parent via Mtareq\NestedReplies\PostReply.
    (new Extend\View)->namespace('itqan-notifications', __DIR__.'/views'),

    (new Extend\Notification)
        ->type(DiscussionRepliedBlueprint::class, PostSerializer::class, ['alert', 'email'])
        ->type(CommentRepliedBlueprint::class, PostSerializer::class, ['alert', 'email'])
        ->beforeSending(FilterDiscussionAuthorFromNewPost::class),

    (new Extend\Event())
        ->listen(Posted::class, SendReplyNotifications::class),

    (new Extend\ServiceProvider())
        ->register(ResolveStringsServiceProvider::class),
];
