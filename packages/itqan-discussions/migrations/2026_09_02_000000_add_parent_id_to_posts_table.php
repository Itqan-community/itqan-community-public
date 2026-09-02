<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Builder;

return [
    'up' => function (Builder $schema) {
        if (!$schema->hasColumn('posts', 'parent_id')) {
            $schema->table('posts', function (Blueprint $table) {
                $table->unsignedInteger('parent_id')->nullable()->index();
                $table->unsignedInteger('reply_count')->default(0);
            });
        }
    },
    'down' => function (Builder $schema) {
        if ($schema->hasColumn('posts', 'parent_id')) {
            $schema->table('posts', function (Blueprint $table) {
                $table->dropColumn(['parent_id', 'reply_count']);
            });
        }
    }
];
