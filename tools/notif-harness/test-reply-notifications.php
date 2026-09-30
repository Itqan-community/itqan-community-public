<?php
// Functional test for Group 2 reply notifications: build the listener with a
// spy NotificationSyncer (no DB writes, no email), fire the listener's
// `handle(Posted $event)` for a real reply, and report which notification
// types would have been delivered and to whom.
//
// Usage (from the staging container):
//   cd /var/www/html && php tools/notif-harness/test-reply-notifications.php

require '/var/www/html/vendor/autoload.php';
$site = require '/var/www/html/site.php';
$app = $site->bootApp();
$c = method_exists($app, 'getContainer') ? $app->getContainer() : $app;

/**
 * A NotificationSyncer that captures every sync() call but writes no
 * notifications and triggers no drivers — pure observation.
 */
class SpyNotificationSyncer extends \Flarum\Notification\NotificationSyncer
{
    /** @var array<int, array{blueprint:string, recipients:int[]}> */
    public array $calls = [];

    public function sync(\Flarum\Notification\Blueprint\BlueprintInterface $blueprint, array $users): void
    {
        $recipients = [];
        foreach ($users as $u) {
            if ($u instanceof \Flarum\User\User) {
                $recipients[] = (int) $u->id;
            }
        }
        $this->calls[] = [
            'blueprint'   => get_class($blueprint) . ' (' . $blueprint::getType() . ')',
            'recipients'  => $recipients,
        ];
    }
}

$spy = new SpyNotificationSyncer();
$listener = new \Itqan\Notifications\Listener\SendReplyNotifications($spy);

// Pick a real reply post that the user pre-inserted into
// `mtareq_nested_replies_parents` (the test fixture). Falls back to the
// most recent comment post in the DB.
$replyId = getenv('TEST_REPLY_ID') ? (int) getenv('TEST_REPLY_ID') : null;

if ($replyId) {
    $reply = \Flarum\Post\Post::find($replyId);
    if (! $reply) {
        fwrite(STDERR, "TEST_REPLY_ID=$replyId not found in posts\n");
        exit(2);
    }
} else {
    $reply = \Flarum\Post\Post::query()
        ->where('number', '>', 1)
        ->where('type', 'comment')
        ->orderByDesc('id')
        ->first();
    if (! $reply) {
        fwrite(STDERR, "no real reply post found\n");
        exit(2);
    }
}

echo "Test reply post id={$reply->id} number={$reply->number} user_id={$reply->user_id} discussion_id={$reply->discussion_id}\n";

// Resolve parent the same way the listener does (mtareq).
$parentId = \Mtareq\NestedReplies\PostReply::query()
    ->where('post_id', $reply->id)
    ->value('parent_post_id');
$parent = $parentId ? \Flarum\Post\Post::find($parentId) : null;
echo "Parent lookup: parent_post_id=" . ($parentId ?? 'NULL') . " parent_exists=" . ($parent ? 'yes' : 'no') . "\n";

$discussion = $reply->discussion;
$author = $discussion ? $discussion->user : null;
echo "Discussion author user_id=" . ($author?->id ?? 'NULL') . "\n";

// Build a Posted event the same way Flarum's Post::post() does. The listener
// only reads $event->post, so we can hand-craft it.
$event = new \Flarum\Post\Event\Posted($reply);

$listener->handle($event);

echo "Spy captured " . count($spy->calls) . " sync() calls:\n";
foreach ($spy->calls as $i => $call) {
    echo sprintf("  [%d] %s -> users %s\n", $i + 1, $call['blueprint'], '[' . implode(',', $call['recipients']) . ']');
}

if (empty($spy->calls)) {
    fwrite(STDERR, "WARN: no notifications fired — check parent fixture or visibility\n");
    exit(1);
}

exit(0);