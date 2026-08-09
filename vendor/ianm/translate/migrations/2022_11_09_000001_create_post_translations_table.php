<?php

/*
 * This file is part of ianm/translate.
 *
 * Copyright (c) 2022 IanM.
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Builder;

return [
    'up' => function (Builder $schema) {
        $schema->create('post_translations', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('post_id')->unsigned();
            $table->string('language', 10);
            $table->mediumText('content')->nullable();
            $table->string('provider');
            $table->dateTime('created_at')->useCurrent()->nullable();
            $table->dateTime('updated_at')->useCurrent()->nullable();
            $table->unique(['post_id', 'language']);

            $table->foreign('post_id')->references('id')->on('posts')->onDelete('cascade');
        });
    },

    'down' => function (Builder $schema) {
        $schema->drop('post_translations');
    }
];
