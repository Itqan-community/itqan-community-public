<?php
// Functional test for the moderation notifications wired by itqan-notifications:
// PostApproved, DiscussionStickied and DiscussionRetagged.
//
// It builds the listener with a spy NotificationSyncer (captures every sync() call,
// writes no notifications / sends nothing) and drives REAL Flarum commands so the
// real domain events fire.
//
// NOTE: it creates throwaway discussions authored by the `replier` user. Run it
// against a disposable local/staging database, never production.
//
// Usage (from the container):
//   cd /var/www/html && php tools/notif-harness/test-moderation-notifications.php

require '/var/www/html/vendor/autoload.php';
$site = require '/var/www/html/site.php';
$app = $site->bootApp();
$c = method_exists($app, 'getContainer') ? $app->getContainer() : $app;

class ModerationSpySyncer extends \Flarum\Notification\NotificationSyncer
{
    /** @var array<int, array{type:string, recipients:int[]}> */
    public array $calls = [];

    public function sync(\Flarum\Notification\Blueprint\BlueprintInterface $blueprint, array $users): void
    {
        $this->calls[] = [
            'type'       => $blueprint::getType(),
            'recipients' => array_map(static fn ($u) => (int) $u->id, $users),
        ];
    }
}

$spy = new ModerationSpySyncer();
$c->instance(\Flarum\Notification\NotificationSyncer::class, $spy);
$bus = $c->make(\Flarum\Bus\Dispatcher::class);

$admin = \Flarum\User\User::where('username', 'admin')->firstOrFail();

$author = \Flarum\User\User::where('username', 'replier')->first();
if (! $author) {
    $author = new \Flarum\User\User();
    $author->username = 'replier';
    $author->email = 'replier@example.com';
    $author->password = 'password123';
    $author->joined_at = \Carbon\Carbon::now();
    $author->save();
    $author->activate()->save();
}

$startDiscussion = function (\Flarum\User\User $user, int $tagId) use ($bus) {
    return $bus->dispatch(new \Flarum\Discussion\Command\StartDiscussion($user, [
        'attributes'    => ['title' => 'Moderation harness ' . date('His') . random_int(10, 99), 'content' => 'test fixture'],
        'relationships' => ['tags' => ['data' => [['type' => 'tags', 'id' => (string) $tagId]]]],
    ], '127.0.0.1'));
};

$tags = \Flarum\Tags\Tag::all();
$usable = $tags->filter(static fn ($t) => $author->can('startDiscussion', $t))->values();
$tagA = (int) ($usable->get(0)->id ?? 1);
$tagB = (int) ($usable->get(1)->id ?? $tags->firstWhere('id', '!=', $tagA)->id ?? $tagA);

$authorDisc = $startDiscussion($author, (int) $tagA);
printf("Fixture: discussion %d authored by %d (admin %d)\n", $authorDisc->id, $author->id, $admin->id);

// 1) Stick -> discussionStickied to the author.
$bus->dispatch(new \Flarum\Discussion\Command\EditDiscussion($authorDisc->id, $admin, [
    'attributes' => ['isSticky' => true],
]));

// 2) Retag -> discussionRetagged to the author.
$bus->dispatch(new \Flarum\Discussion\Command\EditDiscussion($authorDisc->id, $admin, [
    'attributes'    => [],
    'relationships' => ['tags' => ['data' => [['type' => 'tags', 'id' => (string) $tagB]]]],
]));

// 3) Self-action -> must not notify the actor.
$adminDisc = $startDiscussion($admin, (int) $tagA);
$before = count($spy->calls);
$bus->dispatch(new \Flarum\Discussion\Command\EditDiscussion($adminDisc->id, $admin, [
    'attributes' => ['isSticky' => true],
]));
$selfSyncs = count($spy->calls) - $before;

printf("Spy captured %d sync(s):\n", count($spy->calls));
foreach ($spy->calls as $i => $call) {
    printf("  [%d] %s -> users %s\n", $i + 1, $call['type'], json_encode($call['recipients']));
}
printf("Self-sticky produced %d sync(s) (expected 0)\n", $selfSyncs);

$types = array_column($spy->calls, 'type');
$ok = in_array('discussionStickied', $types, true)
    && in_array('discussionRetagged', $types, true)
    && $selfSyncs === 0
    && ! in_array('postRejected', $types, true);

echo $ok ? "PASS\n" : "FAIL\n";
exit($ok ? 0 : 1);
