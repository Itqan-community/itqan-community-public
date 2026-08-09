<?php

use Flarum\Database\Migration;
use Flarum\Group\Group;

return Migration::addPermissions([
    'translateLocale' => [Group::GUEST_ID],
    'translateAll' => [Group::MODERATOR_ID]
]);
