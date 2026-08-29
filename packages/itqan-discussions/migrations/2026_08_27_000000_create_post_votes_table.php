<?php

use Flarum\Database\Migration;
use Illuminate\Database\Schema\Blueprint;

/*
 * One row per (post, user). The primary key is the pair, so a second vote from
 * the same reader replaces the first rather than adding to it, and the
 * uniqueness is enforced by the database instead of by the code that happens
 * to be writing at the time.
 */
return Migration::createTable('post_votes', function (Blueprint $table) {
    $table->unsignedInteger('post_id');
    $table->unsignedInteger('user_id');
    // -1 or 1. A withdrawn vote is a deleted row, not a zero, so `SUM(value)`
    // stays the score and the table holds no rows that mean nothing.
    $table->tinyInteger('value');
    $table->dateTime('created_at')->nullable();

    $table->primary(['post_id', 'user_id']);

    $table->foreign('post_id')->references('id')->on('posts')->onDelete('cascade');
    $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
});
