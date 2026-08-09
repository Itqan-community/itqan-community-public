<?php

use Flarum\Discussion\Event\Started as DiscussionStarted;
use Flarum\Extend;
use Flarum\Post\Event\Posted;
use Flarum\User\Event\Registered;
use Itqan\MailerLite\Api\Controllers\ListGroupsController;
use Itqan\MailerLite\Api\Controllers\ListSubscribersController;
use Itqan\MailerLite\Api\Controllers\ListSyncLogsController;
use Itqan\MailerLite\Api\Controllers\SyncAllSubscribersController;
use Itqan\MailerLite\Api\Controllers\SyncSubscriberController;
use Itqan\MailerLite\Api\Controllers\TestConnectionController;
use Itqan\MailerLite\Api\Controllers\WebhookController;
use Itqan\MailerLite\Console\CheckInactivityCommand;
use Itqan\MailerLite\Console\CheckPowerUsersCommand;
use Itqan\MailerLite\Console\GenerateDigestCommand;
use Itqan\MailerLite\Console\SyncUsersCommand;
use Itqan\MailerLite\Listeners\DiscussionStartedListener;
use Itqan\MailerLite\Listeners\PostCreatedListener;
use Itqan\MailerLite\Listeners\UserRegisteredListener;

return [
    (new Extend\Frontend('admin'))
        ->js(__DIR__ . '/js/dist/admin.js')
        ->css(__DIR__ . '/less/admin.less'),

    new Extend\Locales(__DIR__ . '/locale'),

    // Settings defaults
    (new Extend\Settings())
        ->default('itqan-mailerlite.enabled', false)
        ->default('itqan-mailerlite.api_key', '')
        ->default('itqan-mailerlite.group_new_members', 'new-members')
        ->default('itqan-mailerlite.group_first_posters', 'first-posters')
        ->default('itqan-mailerlite.group_inactive_users', 'inactive-users')
        ->default('itqan-mailerlite.group_power_users', 'power-users')
        ->default('itqan-mailerlite.inactivity_days', 30)
        ->default('itqan-mailerlite.power_user_posts', 50)
        ->default('itqan-mailerlite.webhook_secret', ''),

    // Event listeners
    (new Extend\Event())
        ->listen(Registered::class, UserRegisteredListener::class)
        ->listen(Posted::class, PostCreatedListener::class)
        ->listen(DiscussionStarted::class, DiscussionStartedListener::class),

    // Console commands
    (new Extend\Console())
        ->command(SyncUsersCommand::class)
        ->command(CheckInactivityCommand::class)
        ->command(CheckPowerUsersCommand::class)
        ->command(GenerateDigestCommand::class),

    // Admin API routes
    (new Extend\Routes('api'))
        ->get('/mailerlite/test-connection', 'mailerlite.test-connection', TestConnectionController::class)
        ->get('/mailerlite/sync-logs', 'mailerlite.sync-logs', ListSyncLogsController::class)
        ->get('/mailerlite/subscribers', 'mailerlite.subscribers', ListSubscribersController::class)
        ->post('/mailerlite/sync/{userId}', 'mailerlite.sync-user', SyncSubscriberController::class)
        ->post('/mailerlite/sync-all', 'mailerlite.sync-all', SyncAllSubscribersController::class)
        ->get('/mailerlite/groups', 'mailerlite.groups', ListGroupsController::class),

    // Webhook route (no auth required)
    (new Extend\Routes('api'))
        ->post('/mailerlite/webhook', 'mailerlite.webhook', WebhookController::class),

    // Exempt webhook from CSRF — external callers (MailerLite) have no session token
    (new Extend\Csrf())
        ->exemptRoute('mailerlite.webhook'),
];
