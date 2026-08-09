<?php

/*
 * This file is part of ianm/translate.
 *
 * Copyright (c) 2023 IanM.
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Builder;

return [
    'up' => function (Builder $schema) {
        if (! $schema->hasColumn('discussions', 'detected_lang')) {
            $schema->table('discussions', function (Blueprint $table) {
                $table->string('detected_lang', 2)->index()->nullable();
            });
        }
    },
    'down' => function (Builder $schema) {
        $schema->table('discussions', function (Blueprint $table) {
            $table->dropColumn('detected_lang');
        });
    }
];
