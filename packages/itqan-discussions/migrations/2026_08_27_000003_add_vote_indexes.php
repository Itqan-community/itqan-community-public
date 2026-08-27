<?php

use Flarum\Database\Migration;
use Illuminate\Database\Schema\Blueprint;

/*
 * Added separately from the columns: `Migration::addColumns` has no way to
 * declare an index, and these are the whole reason the columns exist.
 */
return [
    'up' => function (Illuminate\Database\Schema\Builder $schema) {
        $schema->table('discussions', function (Blueprint $table) {
            $table->index('votes', 'discussions_votes_index');
            $table->index('hotness', 'discussions_hotness_index');
        });

        $schema->table('posts', function (Blueprint $table) {
            $table->index('votes', 'posts_votes_index');
        });
    },

    'down' => function (Illuminate\Database\Schema\Builder $schema) {
        $schema->table('discussions', function (Blueprint $table) {
            $table->dropIndex('discussions_votes_index');
            $table->dropIndex('discussions_hotness_index');
        });

        $schema->table('posts', function (Blueprint $table) {
            $table->dropIndex('posts_votes_index');
        });
    },
];
