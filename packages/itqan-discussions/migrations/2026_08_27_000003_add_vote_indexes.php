<?php

use Illuminate\Database\Schema\Builder;
use Illuminate\Database\Schema\Blueprint;

/*
 * Added separately from the columns: `Migration::addColumns` has no way to
 * declare an index, and these are the whole reason the columns exist.
 *
 * Guarded because a database can drift from the `migrations` table (restored
 * snapshot, half-run enable); a bare `add index` then aborts the whole enable
 * with "Duplicate key name" or "Key column doesn't exist".
 */
$indexes = [
    ['discussions', 'votes', 'integer', 'discussions_votes_index'],
    ['discussions', 'hotness', 'double', 'discussions_hotness_index'],
    ['posts', 'votes', 'integer', 'posts_votes_index'],
];

return [
    'up' => function (Builder $schema) use ($indexes) {
        foreach ($indexes as [$table, $column, $type, $name]) {
            if (! $schema->hasColumn($table, $column)) {
                $schema->table($table, fn (Blueprint $t) => $t->$type($column)->default(0));
            }

            $existing = $schema->getConnection()->getDoctrineSchemaManager()->listTableIndexes($table);

            if (! isset($existing[$name])) {
                $schema->table($table, fn (Blueprint $t) => $t->index($column, $name));
            }
        }
    },

    'down' => function (Builder $schema) use ($indexes) {
        foreach ($indexes as [$table, , , $name]) {
            $existing = $schema->getConnection()->getDoctrineSchemaManager()->listTableIndexes($table);

            if (isset($existing[$name])) {
                $schema->table($table, fn (Blueprint $t) => $t->dropIndex($name));
            }
        }
    },
];
