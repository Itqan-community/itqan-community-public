<?php

use Flarum\Database\Migration;
use Illuminate\Database\Schema\Blueprint;

/*
 * A discussion's own score — the score of its first post — plus the ranking
 * used by the "hot" sort.
 *
 * Both are denormalised for the same reason as `posts.votes`: the discussion
 * list is the busiest query on the forum, and it cannot join and aggregate
 * votes on every row. Both are indexed because both are sorted on.
 */
return Migration::addColumns('discussions', [
    'votes' => ['integer', 'default' => 0],
    // Reddit's ranking is a float: a log-scaled score plus an age term.
    'hotness' => ['double', 'default' => 0],
]);
