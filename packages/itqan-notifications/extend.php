<?php

use Flarum\Api\Serializer\DiscussionSerializer;
use Flarum\Api\Serializer\PostSerializer;
use Flarum\Approval\Event\PostWasApproved;
use Flarum\Extend;
use Flarum\Messages\Notification\MessageReceivedBlueprint;
use Flarum\Post\Event\Posted;
use Flarum\Sticky\Event\DiscussionWasStickied;
use Flarum\Tags\Event\DiscussionWasTagged;
use Itqan\Notifications\Listener\SendModerationNotifications;
use Itqan\Notifications\Listener\SendReplyNotifications;
use Itqan\Notifications\Notification\CommentRepliedBlueprint;
use Itqan\Notifications\Notification\DiscussionRepliedBlueprint;
use Itqan\Notifications\Notification\DiscussionRetaggedBlueprint;
use Itqan\Notifications\Notification\DiscussionStickiedBlueprint;
use Itqan\Notifications\Notification\FilterDiscussionAuthorFromNewPost;
use Itqan\Notifications\Notification\PostApprovedBlueprint;
use Itqan\Notifications\Provider\ResolveStringsServiceProvider;

return [
    (new Extend\Frontend('forum'))
        ->js(__DIR__.'/js/dist/forum.js'),

    new Extend\Locales(__DIR__.'/locale'),

    (new Extend\User())
        ->registerPreference('dndEnabled', 'boolval', false)

        // Default notification preferences for the custom blueprints, so they
        // are delivered out of the box on alert, email and push. Core registers
        // alert/email itself; registering explicitly keeps parity with the
        // deployed staging configuration and, importantly, turns push on by
        // default (the PWA driver would otherwise default it to the
        // "pushNotifPreferenceDefaultToEmail" setting).
        ->registerPreference('notify_postApproved_alert', 'boolval', true)
        ->registerPreference('notify_postApproved_email', 'boolval', true)
        ->registerPreference('notify_postApproved_push', 'boolval', true)
        ->registerPreference('notify_discussionStickied_alert', 'boolval', true)
        ->registerPreference('notify_discussionStickied_email', 'boolval', true)
        ->registerPreference('notify_discussionStickied_push', 'boolval', true)
        ->registerPreference('notify_discussionRetagged_alert', 'boolval', true)
        ->registerPreference('notify_discussionRetagged_email', 'boolval', true)
        ->registerPreference('notify_discussionRetagged_push', 'boolval', true)

        // Direct messages were email-only upstream: `flarum/messages` registers
        // the blueprint with the `email` driver alone, so a DM never reached the
        // bell or the phone. It now behaves like every other Itqan notification —
        // alert, email and push, all on by default and all user-toggleable.
        ->registerPreference('notify_messageReceived_alert', 'boolval', true)
        ->registerPreference('notify_messageReceived_email', 'boolval', true)
        ->registerPreference('notify_messageReceived_push', 'boolval', true)

        // The reply notifications already defaulted to alert + email; the PR adds
        // push for them, so give push a sane default too.
        ->registerPreference('notify_discussionReplied_push', 'boolval', true)
        ->registerPreference('notify_commentReplied_push', 'boolval', true),

    (new Extend\View)->namespace('itqan-notifications', __DIR__.'/views'),

    // Shadows the two DM email views so they carry the same greeting, CTA and
    // excerpt block as the rest of our notifications. Prepend wins over the
    // `flarum-messages` namespace; nothing else in that namespace is touched.
    (new Extend\View)->extendNamespace('flarum-messages', __DIR__.'/views'),

    (new Extend\Notification)
        ->type(DiscussionRepliedBlueprint::class, ['alert', 'email', 'push'])
        ->type(CommentRepliedBlueprint::class, ['alert', 'email', 'push'])
        ->type(PostApprovedBlueprint::class, ['alert', 'email', 'push'])
        ->type(DiscussionStickiedBlueprint::class, ['alert', 'email', 'push'])
        ->type(DiscussionRetaggedBlueprint::class, ['alert', 'email', 'push'])
        // Re-registered with the full channel set. This extension declares
        // `flarum/messages` as a dependency, so it always loads after it and
        // this list replaces the upstream `['email']` one.
        ->type(MessageReceivedBlueprint::class, ['alert', 'email', 'push'])
        ->beforeSending(FilterDiscussionAuthorFromNewPost::class),

    (new Extend\Event())
        // Reply notifications (unchanged behaviour).
        ->listen(Posted::class, SendReplyNotifications::class)
        // Moderation notifications. The approval/sticky/tags event classes are
        // referenced as strings, so minimal installs without those extensions
        // simply never dispatch them.
        ->listen(PostWasApproved::class, [SendModerationNotifications::class, 'whenPostWasApproved'])
        ->listen(DiscussionWasStickied::class, [SendModerationNotifications::class, 'whenDiscussionWasStickied'])
        ->listen(DiscussionWasTagged::class, [SendModerationNotifications::class, 'whenDiscussionWasTagged']),

    (new Extend\ServiceProvider())
        ->register(ResolveStringsServiceProvider::class),
];
