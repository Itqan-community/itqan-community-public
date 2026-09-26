<?php

// Run: php packages/flarum-replies-extension/tests/migrate_legacy_vote_data_test.php
// In-memory sqlite, no Flarum boot. Exits non-zero on the first failed assertion.

error_reporting(E_ALL & ~E_DEPRECATED);
require __DIR__.'/../../../vendor/autoload.php';
require __DIR__.'/../src/Vote/Ranking.php';

use Illuminate\Database\Capsule\Manager as DB;
use Illuminate\Database\Schema\Blueprint;
use Mtareq\NestedReplies\Vote\Ranking;

$migration = require __DIR__.'/../migrations/2026_09_23_000002_migrate_legacy_vote_data.php';

function check(bool $ok, string $name): void
{
    echo ($ok ? 'PASS' : 'FAIL')." $name\n";
    if (! $ok) {
        exit(1);
    }
}

function freshDb(bool $withLegacy = true): DB
{
    $db = new DB;
    // Capsule::table() is a static proxy through static::$instance — seed() and
    // the assertions use $db->table(...), so the global instance must be set.
    $db->setAsGlobal();
    $db->addConnection(['driver' => 'sqlite', 'database' => ':memory:']);
    $s = $db->getConnection()->getSchemaBuilder();

    $s->create('discussions', function (Blueprint $t) {
        $t->increments('id');
        $t->unsignedInteger('first_post_id');
        $t->dateTime('created_at');
        $t->integer('votes')->default(0);
        $t->double('hotness')->default(0);
    });
    $s->create('posts', function (Blueprint $t) use ($withLegacy) {
        $t->increments('id');
        $t->unsignedInteger('discussion_id');
        if ($withLegacy) {
            $t->unsignedInteger('parent_id')->nullable();
        }
    });
    if ($withLegacy) {
        $s->create('post_votes', function (Blueprint $t) {
            $t->unsignedInteger('post_id');
            $t->unsignedInteger('user_id');
            $t->tinyInteger('value');
            $t->dateTime('created_at')->nullable();
            $t->primary(['post_id', 'user_id']);
        });
    }
    // mtareq target tables (test copy without the FK clauses; FK enforcement is
    // off by default in sqlite and the real definitions are covered by the
    // extension's own create migration).
    $s->create('mtareq_nested_replies_votes', function (Blueprint $t) {
        $t->increments('id');
        $t->unsignedInteger('post_id');
        $t->unsignedInteger('user_id');
        $t->tinyInteger('value');
        $t->dateTime('created_at')->nullable();
        $t->dateTime('updated_at')->nullable();
        $t->unique(['post_id', 'user_id']);
    });
    $s->create('mtareq_nested_replies_parents', function (Blueprint $t) {
        $t->increments('id');
        $t->unsignedInteger('post_id');
        $t->unsignedInteger('parent_post_id');
        $t->dateTime('created_at')->nullable();
        $t->dateTime('updated_at')->nullable();
        $t->unique('post_id');
    });

    seed($db, $withLegacy);

    return $db;
}

function seed(DB $db, bool $withLegacy): void
{
    // d1: first post 10 has +1,+1,-1 => score 1. Non-first post 11 has +1 (must NOT count).
    $db->table('discussions')->insert(['id' => 1, 'first_post_id' => 10, 'created_at' => '2026-01-01 00:00:00']);
    $db->table('discussions')->insert(['id' => 2, 'first_post_id' => 20, 'created_at' => '2026-01-01 00:00:00']);

    // Valid parents (11->10, 12->11); dangling (20->999, 999 missing) and self (21->21) must be skipped.
    $db->table('posts')->insert(['id' => 10, 'discussion_id' => 1]);
    if ($withLegacy) {
        $db->table('posts')->insert(['id' => 11, 'discussion_id' => 1, 'parent_id' => 10]);
        $db->table('posts')->insert(['id' => 12, 'discussion_id' => 1, 'parent_id' => 11]);
        $db->table('posts')->insert(['id' => 20, 'discussion_id' => 2, 'parent_id' => 999]);
        $db->table('posts')->insert(['id' => 21, 'discussion_id' => 2, 'parent_id' => 21]);
    } else {
        $db->table('posts')->insert(['id' => 11, 'discussion_id' => 1]);
        $db->table('posts')->insert(['id' => 12, 'discussion_id' => 1]);
        $db->table('posts')->insert(['id' => 20, 'discussion_id' => 2]);
        $db->table('posts')->insert(['id' => 21, 'discussion_id' => 2]);
    }

    if ($withLegacy) {
        $db->table('post_votes')->insert(['post_id' => 10, 'user_id' => 1, 'value' => 1, 'created_at' => '2026-01-02 00:00:00']);
        $db->table('post_votes')->insert(['post_id' => 10, 'user_id' => 2, 'value' => 1, 'created_at' => '2026-01-02 00:00:00']);
        $db->table('post_votes')->insert(['post_id' => 10, 'user_id' => 3, 'value' => -1, 'created_at' => '2026-01-02 00:00:00']);
        $db->table('post_votes')->insert(['post_id' => 11, 'user_id' => 4, 'value' => 1, 'created_at' => '2026-01-02 00:00:00']);
    }
    // Already-copied pair: insertOrIgnore must not duplicate it.
    $db->table('mtareq_nested_replies_votes')->insert(['post_id' => 11, 'user_id' => 4, 'value' => 1]);
    // Already-copied link: must not duplicate.
    $db->table('mtareq_nested_replies_parents')->insert(['post_id' => 12, 'parent_post_id' => 11]);
}

function up(array $migration, $schema): ?Throwable
{
    try {
        $migration['up']($schema);

        return null;
    } catch (Throwable $e) {
        return $e;
    }
}

$db = freshDb();
$schema = $db->getConnection()->getSchemaBuilder();

$err = up($migration, $schema);
if ($err !== null) {
    echo 'FAIL up_runsClean -> '.get_class($err).': '.$err->getMessage()."\n";
    exit(1);
}
echo "PASS up_runsClean\n";

check($db->table('mtareq_nested_replies_votes')->count() === 4, 'votes_copied_pairIgnored_noDuplicates: 4 rows (3 new from post_votes + 1 pre-existing unique)');
check($db->table('mtareq_nested_replies_votes')->where('post_id', 10)->count() === 3, 'votes_post10_allThreeCopied');

check($db->table('mtareq_nested_replies_parents')->count() === 2, 'parents_copied_excludesDanglingAndSelf: only 11->10 (12 pre-existing; 999-dangling and self-parent skipped)');

$d1 = $db->table('discussions')->where('id', 1)->first();
$d2 = $db->table('discussions')->where('id', 2)->first();
check((int) $d1->votes === 1, 'discussion1_votes_isFirstPostOnly: +1+1-1 = 1 (post 11\'s vote excluded)');
check((int) $d2->votes === 0, 'discussion2_votes_noVotes_isZero');

$expectedHot = Ranking::hotness(1, new DateTimeImmutable('@'.strtotime('2026-01-01 00:00:00 UTC')));
check(abs((float) $d1->hotness - $expectedHot) < 1e-9, 'discussion1_hotness_equalsRankingFormula: migration seed stays in sync with Ranking');

// Idempotency: run again, expect identical state.
$before = json_encode($db->table('discussions')->orderBy('id')->get(['id', 'votes', 'hotness'])->toArray());
check(up($migration, $schema) === null, 'up_runTwice_noError');
$after = json_encode($db->table('discussions')->orderBy('id')->get(['id', 'votes', 'hotness'])->toArray());
check($before === $after, 'up_runTwice_identicalScores');
check($db->table('mtareq_nested_replies_votes')->count() === 4 && $db->table('mtareq_nested_replies_parents')->count() === 2, 'up_runTwice_noRowDuplication');

// Fresh-install path: legacy sources absent entirely — must still succeed and seed.
$db2 = freshDb(withLegacy: false);
$s2 = $db2->getConnection()->getSchemaBuilder();
check(up($migration, $s2) === null, 'up_noLegacySources_runsClean');
check((int) $db2->table('discussions')->where('id', 1)->first()->votes === 0, 'up_noLegacySources_seedsZeroFromOwnTable');

echo "ALL PASS\n";
