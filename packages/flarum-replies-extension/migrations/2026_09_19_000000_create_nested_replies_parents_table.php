<?php

use Flarum\Database\Migration;
use Illuminate\Database\Schema\Blueprint;

return Migration::createTable('mtareq_nested_replies_parents', function (Blueprint $table) {
    $table->increments('id');
    $table->unsignedInteger('post_id');
    $table->unsignedInteger('parent_post_id');
    $table->timestamps();

    $table->unique('post_id');

    $table->foreign('post_id')->references('id')->on('posts')->cascadeOnDelete();
    $table->foreign('parent_post_id')->references('id')->on('posts')->cascadeOnDelete();
});
