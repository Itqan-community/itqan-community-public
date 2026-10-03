<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Builder;

return [
    'up' => function (Builder $schema) {
        $schema->table('posts', function (Blueprint $table) {
            $table->index(['discussion_id', 'parent_id'], 'posts_discussion_parent_index');
            $table->index(['discussion_id', 'root_id'], 'posts_discussion_root_index');
            $table->index(['discussion_id', 'parent_id', 'votes'], 'posts_discussion_parent_votes_index');
            $table->index(['discussion_id', 'parent_id', 'created_at'], 'posts_discussion_parent_created_index');
        });
    },

    'down' => function (Builder $schema) {
        $schema->table('posts', function (Blueprint $table) {
            $table->dropIndex('posts_discussion_parent_index');
            $table->dropIndex('posts_discussion_root_index');
            $table->dropIndex('posts_discussion_parent_votes_index');
            $table->dropIndex('posts_discussion_parent_created_index');
        });
    },
];
