<?php

/*
 * This file is part of ianm/translate.
 *
 * Copyright (c) 2023 IanM.
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

use Flarum\Database\Migration;
use Illuminate\Database\Schema\Blueprint;

return Migration::createTable(
    'discussion_translations',
    function (Blueprint $table) {
        $table->increments('id');
        $table->unsignedInteger('discussion_id');
        $table->string('language', 10);
        $table->text('translation');
        $table->string('provider');
        $table->boolean('update_needed')->default(false);
        $table->timestamps();
        $table->unique(['discussion_id', 'language']);
        
        $table->foreign('discussion_id')->references('id')->on('discussions')->onDelete('cascade');
    }
);
