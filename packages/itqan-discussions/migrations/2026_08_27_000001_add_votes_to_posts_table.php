<?php

use Flarum\Database\Migration;
use Illuminate\Database\Schema\Blueprint;

/*
 * The running score, kept on the post itself.
 *
 * Sorting replies by score is the point of the feature, and a `SUM()` over
 * post_votes for every row of every page does not survive a discussion with a
 * few hundred replies. The column is written on each vote and read everywhere
 * else.
 */
return Migration::addColumns('posts', [
    'votes' => ['integer', 'default' => 0],
]);
