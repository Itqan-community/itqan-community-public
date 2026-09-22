<?php

namespace Mtareq\NestedReplies;

use Flarum\Database\AbstractModel;

class PostVote extends AbstractModel
{
    protected $table = 'mtareq_nested_replies_votes';

    protected $fillable = ['post_id', 'user_id', 'value'];

    public $timestamps = true;
}
