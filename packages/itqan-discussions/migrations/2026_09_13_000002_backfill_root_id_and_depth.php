<?php

use Illuminate\Database\Schema\Builder;

/**
 * Walk parent_id chains and set root_id / depth for existing comments.
 */
return [
    'up' => function (Builder $schema) {
        $db = $schema->getConnection();

        // Root comments
        $db->table('posts')
            ->where('number', '>', 1)
            ->whereNull('parent_id')
            ->update(['root_id' => null, 'depth' => 0]);

        // OP
        $db->table('posts')
            ->where('number', 1)
            ->update(['root_id' => null, 'depth' => 0]);

        $maxRounds = 50;
        for ($round = 0; $round < $maxRounds; $round++) {
            $updated = $db->update("
                UPDATE posts AS child
                INNER JOIN posts AS parent ON parent.id = child.parent_id
                SET
                    child.root_id = COALESCE(parent.root_id, parent.id),
                    child.depth = CASE
                        WHEN parent.number = 1 OR parent.parent_id IS NULL THEN 1
                        ELSE parent.depth + 1
                    END
                WHERE child.parent_id IS NOT NULL
                  AND child.number > 1
                  AND (
                    child.root_id IS NULL
                    OR child.root_id <> COALESCE(parent.root_id, parent.id)
                    OR child.depth <> CASE
                        WHEN parent.number = 1 OR parent.parent_id IS NULL THEN 1
                        ELSE parent.depth + 1
                    END
                  )
            ");

            if ($updated === 0) {
                break;
            }
        }
    },

    'down' => function (Builder $schema) {
        // no-op
    },
];
