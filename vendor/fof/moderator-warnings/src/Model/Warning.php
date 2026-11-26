<?php

/*
 * This file is part of fof/moderator-warnings
 *
 * Copyright (c) Alexander Skvortsov.
 * Copyright (c) FriendsOfFlarum
 *
 * For detailed copyright and license information, please view the
 * LICENSE file that was distributed with this source code.
 */

namespace FoF\ModeratorWarnings\Model;

use Carbon\Carbon;
use Flarum\Database\AbstractModel;
use Flarum\Database\ScopeVisibilityTrait;
use Flarum\Formatter\Formatter;
use Flarum\Post\Post;
use Flarum\User\User;

/**
 * @property Carbon $created_at
 * @property Carbon $hidden_at
 * @property User $addedByUser
 * @property User $warnedUser
 * @property User|null $hiddenByUser
 * @property int $user_id
 * @property Post|null $post
 * @property int|null $post_id
 * @property string $public_comment
 * @property string $private_comment
 * @property int $strikes
 * @property int|null $hidden_user_id
 * @property int|null $created_user_id
 */
class Warning extends AbstractModel
{
    use ScopeVisibilityTrait;

    protected $table = 'warnings';

    protected $dates = ['created_at', 'hidden_at'];

    /**
     * The text formatter instance.
     *
     * @var \Flarum\Formatter\Formatter
     */
    protected static $formatter;

    /**
     * Get the text formatter instance.
     *
     * @return \Flarum\Formatter\Formatter
     */
    public static function getFormatter()
    {
        return static::$formatter;
    }

    /**
     * Set the text formatter instance.
     *
     * @param \Flarum\Formatter\Formatter $formatter
     */
    public static function setFormatter(Formatter $formatter)
    {
        static::$formatter = $formatter;
    }

    public function warnedUser()
    {
        return $this->hasOne(User::class, 'id', 'user_id');
    }

    public function addedByUser()
    {
        return $this->hasOne(User::class, 'id', 'created_user_id');
    }

    public function hiddenByUser()
    {
        return $this->hasOne(User::class, 'id', 'hidden_user_id');
    }

    public function post()
    {
        return $this->hasOne(Post::class, 'id', 'post_id');
    }

    public static function strikesForUser($user)
    {
        return self::where('user_id', $user->id)->get()->filter(function ($warning) {
            return is_null($warning->hidden_at);
        })->map(function ($warning) {
            return $warning->strikes;
        })->sum();
    }
}
