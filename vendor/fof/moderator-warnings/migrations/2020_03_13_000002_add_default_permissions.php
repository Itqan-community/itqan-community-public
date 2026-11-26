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

use Flarum\Database\Migration;
use Flarum\Group\Group;

return Migration::addPermissions([
    'user.viewWarnings' => Group::MODERATOR_ID,
    'user.manageWarnings' => Group::MODERATOR_ID,
]);
