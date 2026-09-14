<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Builder;

return [
    'up' => function (Builder $schema) {
        $schema->table('posts', function (Blueprint $table) use ($schema) {
            if (! $schema->hasColumn('posts', 'root_id')) {
                $table->unsignedInteger('root_id')->nullable()->index();
            }
            if (! $schema->hasColumn('posts', 'depth')) {
                $table->unsignedSmallInteger('depth')->default(0);
            }
        });
    },

    'down' => function (Builder $schema) {
        $schema->table('posts', function (Blueprint $table) use ($schema) {
            if ($schema->hasColumn('posts', 'root_id')) {
                $table->dropColumn('root_id');
            }
            if ($schema->hasColumn('posts', 'depth')) {
                $table->dropColumn('depth');
            }
        });
    },
];
