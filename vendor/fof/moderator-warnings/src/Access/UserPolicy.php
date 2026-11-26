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

namespace FoF\ModeratorWarnings\Access;

use Flarum\User\Access\AbstractPolicy;
use Flarum\User\User;

class UserPolicy extends AbstractPolicy
{
    /**
     * @param User $actor
     * @param $ability
     * @param User|string $user
     *
     * @return bool|null
     */
    public function can(User $actor, $ability, $user)
    {
        if ($ability === 'user.viewWarnings' && $user instanceof User && $actor->id == $user->id) {
            return $this->allow();
        }
    }
}
