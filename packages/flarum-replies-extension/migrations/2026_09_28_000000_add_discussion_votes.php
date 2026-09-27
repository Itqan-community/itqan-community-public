<?php

use Illuminate\Database\Schema\Builder;
use Illuminate\Database\Schema\Blueprint;

/*
 * A discussion's own score — the score of its first post. Denormalised for one
 * reason: the discussion list is the busiest query on the forum and cannot join
 * and aggregate votes on every row. Indexed because it is sorted on.
 *
 * Every step guarded (hasColumn / listTableIndexes): a drifted database —
 * restored snapshot, half-run enable, or a pre-existing itqan `votes` column —
 * must not abort with "Duplicate column name" or "Duplicate key name".
 */

$indexName = 'discussions_votes_index';

return [
    'up' => function (Builder $schema) use ($indexName) {
        if (! $schema->hasColumn('discussions', 'votes')) {
            $schema->table('discussions', function (Blueprint $t) {
                $t->integer('votes')->default(0);
            });
        }

        $existing = $schema->getConnection()->getDoctrineSchemaManager()->listTableIndexes('discussions');

        if (! isset($existing[$indexName])) {
            $schema->table('discussions', fn (Blueprint $t) => $t->index('votes', $indexName));
        }
    },

    'down' => function (Builder $schema) use ($indexName) {
        $existing = $schema->getConnection()->getDoctrineSchemaManager()->listTableIndexes('discussions');

        if (isset($existing[$indexName])) {
            $schema->table('discussions', fn (Blueprint $t) => $t->dropIndex($indexName));
        }

        if ($schema->hasColumn('discussions', 'votes')) {
            $schema->table('discussions', fn (Blueprint $t) => $t->dropColumn('votes'));
        }
    },
];
