<?php

// Run: php packages/itqan-discussions/tests/vote_indexes_migration_test.php
// Exits non-zero on the first failed assertion. In-memory sqlite, no Flarum boot.

error_reporting(E_ALL & ~E_DEPRECATED);
require __DIR__.'/../../../vendor/autoload.php';

use Illuminate\Database\Capsule\Manager as DB;
use Illuminate\Database\Schema\Blueprint;

$migration = require __DIR__.'/../migrations/2026_08_27_000003_add_vote_indexes.php';

function freshSchema(bool $postsVotes = true)
{
    $db = new DB;
    $db->addConnection(['driver' => 'sqlite', 'database' => ':memory:']);
    $schema = $db->getConnection()->getSchemaBuilder();
    $schema->create('discussions', function (Blueprint $t) {
        $t->increments('id');
        $t->integer('votes')->default(0);
        $t->double('hotness')->default(0);
    });
    $schema->create('posts', function (Blueprint $t) use ($postsVotes) {
        $t->increments('id');
        if ($postsVotes) {
            $t->integer('votes')->default(0);
        }
    });

    return $schema;
}

function indexes($schema, string $table): array
{
    return array_keys($schema->getConnection()->getDoctrineSchemaManager()->listTableIndexes($table));
}

function check(bool $ok, string $name): void
{
    echo ($ok ? 'PASS' : 'FAIL')." $name\n";
    if (! $ok) {
        exit(1);
    }
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

$s = freshSchema();
check(up($migration, $s) === null, 'up_cleanSchema_createsIndexes: no error');
check(in_array('discussions_votes_index', indexes($s, 'discussions'), true)
    && in_array('discussions_hotness_index', indexes($s, 'discussions'), true)
    && in_array('posts_votes_index', indexes($s, 'posts'), true), 'up_cleanSchema_createsIndexes: all three exist');

$s = freshSchema();
up($migration, $s);
$e = up($migration, $s);
check($e === null, 'up_runTwice_isIdempotent: '.($e ? $e->getMessage() : 'ok'));

$s = freshSchema();
$s->table('discussions', fn (Blueprint $t) => $t->index('votes', 'discussions_votes_index'));
$e = up($migration, $s);
check($e === null, 'up_indexAlreadyExists_skipsIt: '.($e ? $e->getMessage() : 'ok'));
check(in_array('posts_votes_index', indexes($s, 'posts'), true), 'up_indexAlreadyExists_stillAddsMissingOnes');

$s = freshSchema(postsVotes: false);
$e = up($migration, $s);
check($e === null, 'up_postsVotesColumnMissing_addsColumnThenIndex: '.($e ? $e->getMessage() : 'ok'));
check($s->hasColumn('posts', 'votes') && in_array('posts_votes_index', indexes($s, 'posts'), true), 'up_postsVotesColumnMissing_columnAndIndexExist');

echo "ALL PASS\n";
