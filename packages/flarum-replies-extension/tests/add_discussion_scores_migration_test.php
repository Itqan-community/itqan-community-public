<?php

// Run: php packages/flarum-replies-extension/tests/add_discussion_scores_migration_test.php
// In-memory sqlite, no Flarum boot. Exits non-zero on the first failed assertion.

error_reporting(E_ALL & ~E_DEPRECATED);
require __DIR__.'/../../../vendor/autoload.php';

use Illuminate\Database\Capsule\Manager as DB;
use Illuminate\Database\Schema\Blueprint;

$migration = require __DIR__.'/../migrations/2026_09_23_000001_add_discussion_scores.php';

function freshSchema(bool $preAddColumns = false)
{
    $db = new DB;
    $db->addConnection(['driver' => 'sqlite', 'database' => ':memory:']);
    $schema = $db->getConnection()->getSchemaBuilder();
    $schema->create('discussions', function (Blueprint $t) use ($preAddColumns) {
        $t->increments('id');
        if ($preAddColumns) {
            $t->integer('votes')->default(0);
            $t->double('hotness')->default(0);
        }
    });

    return $schema;
}

function indexes($schema): array
{
    return array_keys($schema->getConnection()->getDoctrineSchemaManager()->listTableIndexes('discussions'));
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
check(up($migration, $s) === null, 'up_cleanSchema: no error');
check($s->hasColumn('discussions', 'votes') && $s->hasColumn('discussions', 'hotness'), 'up_cleanSchema_addsBothColumns');
check(in_array('discussions_votes_index', indexes($s), true) && in_array('discussions_hotness_index', indexes($s), true), 'up_cleanSchema_addsBothIndexes');

$s = freshSchema();
up($migration, $s);
check(up($migration, $s) === null, 'up_runTwice_isIdempotent');

$s = freshSchema(preAddColumns: true); // columns must exist for the pre-created index to reference them
$s->getConnection()->statement('CREATE INDEX discussions_votes_index ON discussions (votes)');
check(up($migration, $s) === null, 'up_oneIndexPreexists_noDuplicateError');
check(in_array('discussions_hotness_index', indexes($s), true), 'up_oneIndexPreexisting_stillAddsTheOther');

$s = freshSchema(preAddColumns: true);
check(up($migration, $s) === null, 'up_columnsPreexist_noDuplicateError');
check(in_array('discussions_votes_index', indexes($s), true) && in_array('discussions_hotness_index', indexes($s), true), 'up_columnsPreexist_stillAddsIndexes');

$s = freshSchema();
up($migration, $s);
try {
    $migration['down']($s);
    // The auto-created `primary` PK index always stays — only our two indexes
    // and our two columns are the down() contract.
    $remaining = indexes($s);
    check(
        ! $s->hasColumn('discussions', 'votes')
        && ! $s->hasColumn('discussions', 'hotness')
        && ! in_array('discussions_votes_index', $remaining, true)
        && ! in_array('discussions_hotness_index', $remaining, true),
        'down_dropsIndexesAndColumns'
    );
} catch (Throwable $e) {
    check(false, 'down_dropsIndexesAndColumns: '.$e->getMessage());
}

echo "ALL PASS\n";
