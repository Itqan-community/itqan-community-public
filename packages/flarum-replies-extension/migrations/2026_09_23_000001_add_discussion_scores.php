<?php

use Illuminate\Database\Schema\Builder;
use Illuminate\Database\Schema\Blueprint;

/*
 * A discussion's own score — the score of its first post — plus the ranking the
 * "hot" sort orders by. Both denormalised for one reason: the discussion list
 * is the busiest query on the forum and cannot join and aggregate votes on
 * every row. Both indexed because both are sorted on.
 *
 * Every step guarded (hasColumn / listTableIndexes): a drifted database —
 * restored snapshot, half-run enable — must not abort with "Duplicate column
 * name" or "Duplicate key name".
 */

$indexes = [
    'votes' => 'discussions_votes_index',
    'hotness' => 'discussions_hotness_index',
];

return [
    'up' => function (Builder $schema) use ($indexes) {
        foreach (array_keys($indexes) as $column) {
            if (! $schema->hasColumn('discussions', $column)) {
                $schema->table('discussions', function (Blueprint $t) use ($column) {
                    $column === 'votes' ? $t->integer('votes')->default(0) : $t->double('hotness')->default(0);
                });
            }
        }

        $existing = $schema->getConnection()->getDoctrineSchemaManager()->listTableIndexes('discussions');

        foreach ($indexes as $column => $name) {
            if (! isset($existing[$name])) {
                $schema->table('discussions', fn (Blueprint $t) => $t->index($column, $name));
            }
        }
    },

    'down' => function (Builder $schema) use ($indexes) {
        $existing = $schema->getConnection()->getDoctrineSchemaManager()->listTableIndexes('discussions');

        foreach ($indexes as $name) {
            if (isset($existing[$name])) {
                $schema->table('discussions', fn (Blueprint $t) => $t->dropIndex($name));
            }
        }

        foreach (array_keys($indexes) as $column) {
            if ($schema->hasColumn('discussions', $column)) {
                $schema->table('discussions', fn (Blueprint $t) => $t->dropColumn($column));
            }
        }
    },
];
