<?php

use Flarum\Database\Migration;
use Illuminate\Database\Schema\Blueprint;

return Migration::createTable('mtareq_nested_replies_votes', function (Blueprint $table) {
    $table->increments('id');
    $table->unsignedInteger('post_id');
    $table->unsignedInteger('user_id');
    $table->tinyInteger('value'); // 1 = upvote, -1 = downvote
    $table->timestamps();

    $table->unique(['post_id', 'user_id']);
    $table->index('post_id');
    $table->index('user_id');
});
