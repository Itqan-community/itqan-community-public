<?php

// Run: php packages/flarum-replies-extension/tests/vote_counts_test.php
// In-memory sqlite + a minimal test actor, no Flarum boot.
// Exits non-zero on the first failed assertion.

error_reporting(E_ALL & ~E_DEPRECATED);
require __DIR__.'/../../../vendor/autoload.php';
require __DIR__.'/../src/PostVote.php'; // VoteCounts calls PostVote::query()
require __DIR__.'/../src/Vote/VoteCounts.php';

use Illuminate\Database\Capsule\Manager as DB;
use Illuminate\Database\Schema\Blueprint;
use Mtareq\NestedReplies\Vote\VoteCounts;

function check(bool $ok, string $name): void
{
    echo ($ok ? 'PASS' : 'FAIL')." $name\n";
    if (! $ok) {
        exit(1);
    }
}

$db = new DB;
$db->setAsGlobal();
$db->bootEloquent();
$db->addConnection(['driver' => 'sqlite', 'database' => ':memory:']);
$s = $db->getConnection()->getSchemaBuilder();

// primeOwnForDiscussion() subqueries this table for a discussion's post ids.
$s->create('posts', function (Blueprint $t) {
    $t->increments('id');
    $t->unsignedInteger('discussion_id');
});

$s->create('mtareq_nested_replies_votes', function (Blueprint $t) {
    $t->increments('id');
    $t->unsignedInteger('post_id');
    $t->unsignedInteger('user_id');
    $t->tinyInteger('value');
    $t->dateTime('created_at')->nullable();
    $t->dateTime('updated_at')->nullable();
    $t->unique(['post_id', 'user_id']);
});

$db->table('mtareq_nested_replies_votes')->insert([
    ['post_id' => 1, 'user_id' => 7, 'value' => 1],
    ['post_id' => 1, 'user_id' => 8, 'value' => -1],
    ['post_id' => 2, 'user_id' => 9, 'value' => 1],
    ['post_id' => 5, 'user_id' => 7, 'value' => -1],
]);

// Minimal actor stand-in: VoteCounts only reads ->exists / ->id (or null).
$actor = new class { public $id = 7; public $exists = true; };
$guest = new class { public $exists = false; };

VoteCounts::clear();

$sums = VoteCounts::forPosts([1, 2, 3], $actor);
check($sums == [1 => 0, 2 => 1, 3 => 0], 'forPosts_returnsAllRequestedIds_zeroWhenNone: mixed signed sums');

$mine = VoteCounts::userVotes([1, 5], $actor);
check($mine == [1 => 'up', 5 => 'down'], 'userVotes_mapsSignedValuesToUpDown_forActor');

$guestVotes = VoteCounts::userVotes([1], $guest);
check($guestVotes == [1 => null], 'userVotes_guestGetsNull');
check(VoteCounts::forPosts([1], $guest)[1] === 0, 'forPosts_guestStillGetsSums');

// Memoization: a second identical call must not re-query.
$n = 0;
$db->getConnection()->enableQueryLog();
VoteCounts::clear();
VoteCounts::forPosts([1, 2], $actor);
$n = count($db->getConnection()->getQueryLog());
VoteCounts::forPosts([1, 2], $actor);
check(count($db->getConnection()->getQueryLog()) === $n, 'forPosts_repeatIds_hitMemo_noSecondQuery');

// Missing ids get fetched on demand without refetching known ones.
VoteCounts::forPosts([99], $actor);
check(count($db->getConnection()->getQueryLog()) === $n + 1, 'forPosts_unknownId_runsExactlyOneNewQuery');
check(VoteCounts::forPosts([99], $actor) === [99 => 0], 'forPosts_unknownId_cachedAsZero');

// prime() batches many ids in one actor-independent query.
VoteCounts::clear();
$db->getConnection()->flushQueryLog();
VoteCounts::prime([2, 5]);
check(count($db->getConnection()->getQueryLog()) === 1, 'prime_manyIds_oneQuery');
check(VoteCounts::forPosts([2, 5], $actor) == [2 => 1, 5 => -1], 'prime_thenForPosts_servedFromMemo');

// Own votes are memoized per discussion: a whole stream costs one query.
VoteCounts::clear();
VoteCounts::forPosts([1], $actor); // setup: memo id 1's SUM first, so the count below measures own-vote queries only
$db->getConnection()->flushQueryLog();
VoteCounts::primeOwnForDiscussion(9, $actor); // discussion 9 -> posts table empty here: one no-op batch query
VoteCounts::userVotes([1], $actor);           // sums memo-hit; id 1 not covered by the batch -> exactly one own query
check(count($db->getConnection()->getQueryLog()) === 2, 'primeOwn_thenUserVotes_ownQueryOnlyForUncoveredIds');
check(VoteCounts::userVotes([1], $actor)[1] === 'up', 'userVotes_afterBatching_stillCorrect');

VoteCounts::clear();
check(VoteCounts::forPosts([1], $actor) === [1 => 0], 'clear_resetsMemo');

echo "ALL PASS\n";
