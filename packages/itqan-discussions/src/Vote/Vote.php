<?php

namespace Itqan\Discussions\Vote;

use Flarum\Database\AbstractModel;
use Flarum\Post\Post;
use Flarum\User\User;

/**
 * A single reader's verdict on a single post.
 *
 * @property int $post_id
 * @property int $user_id
 * @property int $value  -1 or 1
 * @property \Carbon\Carbon|null $created_at
 */
class Vote extends AbstractModel
{
    public const UP = 1;
    public const DOWN = -1;

    protected $table = 'post_votes';

    protected $dates = ['created_at'];

    // The key is the (post, user) pair, so there is no incrementing id to
    // hand back after an insert.
    public $incrementing = false;

    protected $primaryKey = null;

    protected $casts = [
        'value' => 'int',
    ];

    public function post()
    {
        return $this->belongsTo(Post::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public static function isValidValue($value): bool
    {
        return $value === self::UP || $value === self::DOWN;
    }
}
