<?php

namespace Itqan\Reactions;

use Flarum\Database\AbstractModel;
use Flarum\Post\Post;
use Flarum\User\User;

/**
 * @property int $id
 * @property int $post_id
 * @property int $user_id
 * @property int $reaction_id
 */
class Reaction extends AbstractModel
{
    protected $table = 'itqan_post_reactions';

    public $timestamps = false;

    public function post()
    {
        return $this->belongsTo(Post::class, 'post_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function type()
    {
        return $this->belongsTo(ReactionType::class, 'reaction_id');
    }
}
