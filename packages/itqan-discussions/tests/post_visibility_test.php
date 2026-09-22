<?php

// Run: php packages/itqan-discussions/tests/post_visibility_test.php
// Exits non-zero on the first failed assertion. No database: the "can this actor see these
// posts" query is replaced by a spy, so the tests count how many times it would have run.

error_reporting(E_ALL & ~E_DEPRECATED);
require __DIR__.'/../../../vendor/autoload.php';

use Flarum\Discussion\Discussion;
use Flarum\Http\RequestUtil;
use Flarum\Post\Post;
use Flarum\User\User;
use Illuminate\Database\Eloquent\Collection;
use Flarum\User\Access\AbstractPolicy;
use Itqan\Discussions\Access\PostPolicy;
use Itqan\Discussions\Access\PostVisibility;
use Itqan\Discussions\Api\PrefetchPostVisibility;
use Laminas\Diactoros\ServerRequest;

/** Stands in for `Post::whereVisibleTo($actor)->whereIn('id', $ids)->pluck('id')` and records every call. */
class VisibilitySpy
{
    public array $calls = [];

    /** @param array<int, int[]> $visible actor id => post ids that actor may see */
    public function __construct(private array $visible) {}

    public function __invoke($actor, array $ids): array
    {
        $this->calls[] = ['actor' => (int) $actor->id, 'ids' => $ids];

        return array_values(array_intersect($ids, $this->visible[(int) $actor->id] ?? []));
    }
}

function actor(int $id): User
{
    $u = new User();
    $u->id = $id;

    return $u;
}

function post(int $id): Post
{
    $p = new Post();
    $p->id = $id;

    return $p;
}

function discussion(int $id, ?Post $first = null, ?array $posts = null): Discussion
{
    $d = new Discussion();
    $d->id = $id;
    if ($first !== null) {
        $d->setRelation('firstPost', $first);
    }
    if ($posts !== null) {
        $d->setRelation('posts', new Collection($posts));
    }

    return $d;
}

function check(bool $ok, string $name): void
{
    echo ($ok ? 'PASS' : 'FAIL')." $name\n";
    if (! $ok) {
        exit(1);
    }
}

$all20 = range(1, 20);
$posts20 = array_map('post', $all20);

// ---- PostVisibility -------------------------------------------------------------------

$spy = new VisibilitySpy([7 => $all20]);
$v = new PostVisibility($spy);
$v->prefetch(actor(7), $posts20);
$results = array_map(fn (Post $p) => $v->isVisible(actor(7), $p), $posts20);
check(count($spy->calls) === 1, 'prefetch_twentyPosts_runsOneQueryNotTwenty');
check(count($spy->calls[0]['ids']) === 20, 'prefetch_twentyPosts_asksAboutAllTwentyIds');
check(! in_array(false, $results, true), 'isVisible_afterPrefetch_answersFromMemoWithoutMoreQueries');

$spy = new VisibilitySpy([7 => [1, 3]]);
$v = new PostVisibility($spy);
$v->prefetch(actor(7), array_map('post', [1, 2, 3, 4]));
check($v->isVisible(actor(7), post(1)) && $v->isVisible(actor(7), post(3)), 'isVisible_visiblePosts_true');
check(! $v->isVisible(actor(7), post(2)) && ! $v->isVisible(actor(7), post(4)), 'isVisible_invisiblePosts_false_noLeak');
check(count($spy->calls) === 1, 'isVisible_prefetchedInvisible_doesNotRequery');

$spy = new VisibilitySpy([7 => [9]]);
$v = new PostVisibility($spy);
check($v->isVisible(actor(7), post(9)) === true, 'isVisible_notPrefetched_fallsBackToSingleQuery');
check($spy->calls === [['actor' => 7, 'ids' => [9]]], 'isVisible_notPrefetched_queriesOnlyThatOnePost');
$v->isVisible(actor(7), post(9));
check(count($spy->calls) === 1, 'isVisible_repeatedAfterFallback_isMemoised');
check($v->isVisible(actor(7), post(10)) === false && count($spy->calls) === 2, 'isVisible_unknownInvisiblePost_false');

$spy = new VisibilitySpy([1 => [5], 2 => []]);
$v = new PostVisibility($spy);
$v->prefetch(actor(1), [post(5)]);
$v->prefetch(actor(2), [post(5)]);
check($v->isVisible(actor(1), post(5)) === true && $v->isVisible(actor(2), post(5)) === false, 'isVisible_differentActors_neverShareAnswers');
check(count($spy->calls) === 2, 'prefetch_secondActor_queriesAgain');

$spy = new VisibilitySpy([7 => [1, 2, 3]]);
$v = new PostVisibility($spy);
$v->prefetch(actor(7), [post(1), post(2)]);
$v->prefetch(actor(7), [post(2), post(3)]);
check(count($spy->calls) === 2 && $spy->calls[1]['ids'] === [3], 'prefetch_alreadyKnownIds_notAskedAgain');

$spy = new VisibilitySpy([7 => [1]]);
$v = new PostVisibility($spy);
$v->prefetch(actor(7), []);
$v->prefetch(actor(7), [post(1), post(1), post(1)]);
check(count($spy->calls) === 1 && $spy->calls[0]['ids'] === [1], 'prefetch_emptyAndDuplicates_noQueryForEmptyAndIdsDeduped');

$v = new PostVisibility(fn ($actor, array $ids) => ['1', '2']); // a driver may hand ids back as strings
$v->prefetch(actor(7), [post(1), post(2), post(3)]);
check($v->isVisible(actor(7), post(1)) && $v->isVisible(actor(7), post(2)) && ! $v->isVisible(actor(7), post(3)), 'prefetch_stringIdsFromDriver_stillMatch');

$spy = new VisibilitySpy([0 => [4]]);
$v = new PostVisibility($spy);
$guest = new User(); // guests have no id
$v->prefetch($guest, [post(4), post(5)]);
check($v->isVisible($guest, post(4)) === true && $v->isVisible($guest, post(5)) === false, 'isVisible_guestActor_works');

// ---- PrefetchPostVisibility (the hook run before the API document is built) ------------------

// Returns the PostVisibility the hook filled, i.e. what the policy would read from.
function run(VisibilitySpy $spy, $data, User $as): PostVisibility
{
    $vis = new PostVisibility($spy);
    (new PrefetchPostVisibility($vis))(null, $data, RequestUtil::withActor(new ServerRequest(), $as));

    return $vis;
}

// Discussion list: one first post per discussion.
$spy = new VisibilitySpy([7 => $all20]);
$list = new Collection(array_map(fn ($i) => discussion($i, post(100 + $i)), $all20));
$vis = run($spy, $list, actor(7));
check(count($spy->calls) === 1 && count($spy->calls[0]['ids']) === 20, 'hook_discussionList_prefetchesAllFirstPostsInOneQuery');
$vis->isVisible(actor(7), post(105));
check(count($spy->calls) === 1, 'hook_discussionList_thenPolicyLookupCostsNothing');

// Discussion page, the REAL shape: ShowDiscussionController::includePosts starts from every visible post id
// (plain ints) and splices the loaded page of Post models over the front of that array. Reading ->id off
// an int is a PHP warning per unloaded post, so warnings are turned into failures for this case.
$spy = new VisibilitySpy([7 => range(1, 20)]);
$ids = range(1, 30);
array_splice($ids, 0, 20, array_map('post', range(1, 20)));
set_error_handler(function (int $no, string $msg) {
    throw new ErrorException($msg, 0, $no);
});
try {
    run($spy, discussion(1, null, $ids), actor(7));
    check(count($spy->calls) === 1 && $spy->calls[0]['ids'] === range(1, 20), 'hook_showDiscussionRealShape_prefetchesOnlyLoadedModelsAndIgnoresBareIds');
} catch (Throwable $e) {
    check(false, 'hook_showDiscussionRealShape_prefetchesOnlyLoadedModelsAndIgnoresBareIds (threw '.$e->getMessage().')');
} finally {
    restore_error_handler();
}

// A first post that an extension also loaded is not counted twice.
$spy = new VisibilitySpy([7 => [1, 2]]);
run($spy, discussion(1, post(1), [post(1), post(2)]), actor(7));
check(count($spy->calls) === 1 && $spy->calls[0]['ids'] === [1, 2], 'hook_firstPostAlsoInPosts_dedupedInOneQuery');

// Post list (/api/posts).
$spy = new VisibilitySpy([7 => [11, 12]]);
run($spy, new Collection([post(11), post(12)]), actor(7));
check(count($spy->calls) === 1 && $spy->calls[0]['ids'] === [11, 12], 'hook_postList_prefetchesThePosts');

// Relations that were NOT eager-loaded must not be lazy-loaded here (there is no database to load from).
$spy = new VisibilitySpy([7 => []]);
try {
    run($spy, new Collection([discussion(1), discussion(2)]), actor(7));
    check(count($spy->calls) === 0, 'hook_relationsNotLoaded_noLazyLoadAndNoQuery');
} catch (Throwable $e) {
    check(false, 'hook_relationsNotLoaded_noLazyLoadAndNoQuery (threw '.get_class($e).')');
}

// Nothing to do: empty data, and a discussion with a null first post.
$spy = new VisibilitySpy([7 => []]);
run($spy, new Collection(), actor(7));
run($spy, new Collection([discussion(1, null, [])]), actor(7));
check(count($spy->calls) === 0, 'hook_emptyOrNullData_noQuery');

// ---- PostPolicy::vote: the security claim itself ---------------------------------------------

function ownedPost(int $id, int $authorId): Post
{
    $p = post($id);
    $p->user_id = $authorId;

    return $p;
}

// Not visible to the actor: denied, so the vote score of a post they cannot see never leaks.
$spy = new VisibilitySpy([7 => [1]]);
$policy = new PostPolicy(new PostVisibility($spy));
check($policy->vote(actor(7), ownedPost(2, 99)) === AbstractPolicy::DENY, 'vote_postActorCannotSee_denied');
check($policy->vote(actor(7), ownedPost(1, 99)) === AbstractPolicy::ALLOW, 'vote_visiblePostByAnotherUser_allowed');

// Own post: denied before any visibility work at all.
$spy = new VisibilitySpy([7 => [5]]);
$policy = new PostPolicy(new PostVisibility($spy));
check($policy->vote(actor(7), ownedPost(5, 7)) === AbstractPolicy::DENY && count($spy->calls) === 0, 'vote_ownPost_deniedWithoutAnyVisibilityQuery');

// The point of the change: after the hook ran, 20 policy checks cost no further queries.
$spy = new VisibilitySpy([7 => $all20]);
$shared = new PostVisibility($spy);
$policy = new PostPolicy($shared);
$shared->prefetch(actor(7), $posts20);
$allowed = 0;
foreach ($all20 as $id) {
    $allowed += $policy->vote(actor(7), ownedPost($id, 99)) === AbstractPolicy::ALLOW ? 1 : 0;
}
check($allowed === 20 && count($spy->calls) === 1, 'vote_twentyPostsAfterPrefetch_allAnsweredFromOneQuery');

// What the container builds (no injected resolver) must construct without touching the database.
$real = new PostVisibility();
$real->prefetch(actor(1), []);
check(true, 'construct_defaultResolver_buildsAndEmptyPrefetchNeedsNoDatabase');

echo "ALL PASS\n";
