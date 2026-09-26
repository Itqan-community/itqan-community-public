<?php

use Flarum\Extend;
use Flarum\Post\Event\Posted;
use Flarum\Post\PostSerializer;
use Itqan\Notifications\Listener\SendCommentRepliedNotification;
use Itqan\Notifications\Notification\CommentRepliedBlueprint;

return [
    (new Extend\Frontend('forum'))
        ->js(__DIR__.'/js/dist/forum.js'),

    new Extend\Locales(__DIR__.'/locale'),

    (new Extend\User())
        ->registerPreference('dndEnabled', 'boolval', false),

    // Register the commentReplied notification type (alert + email)
    (new Extend\Notification())
        ->type(CommentRepliedBlueprint::class, PostSerializer::class, ['alert', 'email']),

    // Listen for new posts and dispatch commentReplied notifications
    (new Extend\Event())
        ->listen(Posted::class, SendCommentRepliedNotification::class),

    // Register the Blade view namespace for email templates.
    // NOTE (Task 8 self-review): the ported branch used
    // `new Extend\View(__DIR__.'/views')` — Extend\View has NO constructor in
    // core 1.8 (verified: vendor/flarum/core/src/Extend/View.php), and core
    // never auto-registers extension view namespaces, so that line silently
    // registered nothing and the email template would not resolve. The
    // namespace must match CommentRepliedBlueprint's getEmailView():
    // `itqan-notifications::emails.commentReplied`.
    (new Extend\View())
        ->namespace('itqan-notifications', __DIR__.'/views'),
];
