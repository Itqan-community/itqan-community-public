<?php

use Illuminate\Database\Schema\Builder;

/**
 * Preserves the four discussions that used `the-turk/flarum-stickiest`.
 *
 * The extension has no Flarum 2.0 build (it patches APIs removed in 2.0), so
 * "stick to top of tag" is dropped. Converting the flags to normal sticky keeps
 * those discussions pinned; they become globally pinned rather than tag-pinned.
 *
 * Idempotent.
 */
return [
    'up' => function (Builder $schema) {
        $db = $schema->getConnection();
        $tables = $schema->getConnection()->getSchemaBuilder();

        if (! $tables->hasColumn('discussions', 'is_stickiest')) {
            return;
        }

        $query = $db->table('discussions')->where('is_stickiest', 1);

        if ($tables->hasColumn('discussions', 'is_tag_sticky')) {
            $query->orWhere('is_tag_sticky', 1);
        }

        $query->update(['is_sticky' => 1]);
    },

    'down' => function (Builder $schema) {
        //
    },
];
