<?php
// Boots Flarum and renders EVERY notification type: email subject + body (en,ar) and push payload.
require '/var/www/html/vendor/autoload.php';
$site = require '/var/www/html/site.php';
$app = $site->bootApp();
$c = method_exists($app, 'getContainer') ? $app->getContainer() : $app;

$view = $c->make('view');
$translator = $c->make('translator');
$builder = $c->make(Askvortsov\FlarumPWA\NotificationBuilder::class);

$user = Flarum\User\User::whereNotNull('username')->orderBy('id')->first();
$reply = Flarum\Post\Post::where('number', '>', 1)->has('discussion')->has('user')->first();
try {
    $parent = Flarum\Post\Post::whereNotNull('parent_id')->first() ?: $reply;
} catch (\Throwable $e) {
    $parent = $reply; // staging (mtareq) has no posts.parent_id column
}
$discussion = $reply->discussion;
$mentioned = Flarum\Post\Post::where('number', '>', 1)->has('user')->first() ?: $reply;

$types = [
    'newPost' => new Flarum\Subscriptions\Notification\NewPostBlueprint($reply),
    'userMentioned' => new Flarum\Mentions\Notification\UserMentionedBlueprint($mentioned),
    'postMentioned' => new Flarum\Mentions\Notification\PostMentionedBlueprint($mentioned, $reply),
    'groupMentioned' => new Flarum\Mentions\Notification\GroupMentionedBlueprint($mentioned),
    'postLiked' => new Flarum\Likes\Notification\PostLikedBlueprint($mentioned, $user),
    'userSuspended' => new Flarum\Suspend\Notification\UserSuspendedBlueprint($user),
    'userUnsuspended' => new Flarum\Suspend\Notification\UserUnsuspendedBlueprint($user),
    // discussionReplied / commentReplied are appended conditionally below (staging uses mtareq).
    'newDiscussionInTag' => new FoF\FollowTags\Notifications\NewDiscussionBlueprint($discussion, $discussion->firstPost),
    'newPostInTag' => new FoF\FollowTags\Notifications\NewPostBlueprint($reply),
    'newDiscussionTag' => new FoF\FollowTags\Notifications\NewDiscussionTagBlueprint($user, $discussion, $discussion->firstPost),
    'newDiscussionByUser' => new IanM\FollowUsers\Notifications\NewDiscussionBlueprint($discussion, $discussion->firstPost),
    'newPostByUser' => new IanM\FollowUsers\Notifications\NewPostByUserBlueprint($reply),
    'newFollower' => new IanM\FollowUsers\Notifications\NewFollowerBlueprint($user),
    'newUnfollower' => new IanM\FollowUsers\Notifications\NewUnfollowerBlueprint($user),
];

// Only include the reply-notification types when their extension is present.
// Main has them under Itqan\Discussions; staging has moved them under
// Itqan\Notifications (with mtareq-driven parent lookup). Pick whichever
// namespace is currently loaded.
$discussionRepliedClass = null;
$commentRepliedClass = null;
foreach (
    [
        \Itqan\Notifications\Notification\DiscussionRepliedBlueprint::class,
        \Itqan\Discussions\Notification\DiscussionRepliedBlueprint::class,
    ] as $candidate
) {
    if (class_exists($candidate)) {
        $discussionRepliedClass = $candidate;
        break;
    }
}
foreach (
    [
        \Itqan\Notifications\Notification\CommentRepliedBlueprint::class,
        \Itqan\Discussions\Notification\CommentRepliedBlueprint::class,
    ] as $candidate
) {
    if (class_exists($candidate)) {
        $commentRepliedClass = $candidate;
        break;
    }
}
if ($discussionRepliedClass) {
    $types['discussionReplied'] = new $discussionRepliedClass($reply);
}
if ($commentRepliedClass) {
    $types['commentReplied'] = new $commentRepliedClass($reply, $parent);
}

$out = [];
foreach ($types as $name => $bp) {
    foreach (['en', 'ar'] as $loc) {
        $translator->setLocale($loc);
        $out["$name|$loc|email_subject"] = method_exists($bp, 'getEmailSubject') ? $bp->getEmailSubject($translator) : '';
        try {
            $vn = method_exists($bp, 'getEmailView') ? ($bp->getEmailView()['text'] ?? null) : null;
            $out["$name|$loc|email_body"] = $vn ? strip_tags($view->make($vn, ['blueprint' => $bp, 'user' => $user])->render()) : '';
        } catch (\Throwable $e) {
            $out["$name|$loc|email_body"] = 'ERR: '.$e->getMessage();
        }
    }
    $translator->setLocale('en');
    try {
        $m = $builder->build($bp);
        $out["$name|push|title"] = $m->title();
        $out["$name|push|body"] = strip_tags((string) $m->body());
        $out["$name|push|url"] = $m->url();
    } catch (\Throwable $e) {
        $out["$name|push|title"] = 'ERR: '.$e->getMessage();
    }
}
file_put_contents('/var/www/html/storage/notif-harness.json', json_encode($out, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
echo 'wrote storage/notif-harness.json ('.count($out)." entries)\n";
