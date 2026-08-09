<?php

use Flarum\Database\Migration;

return Migration::addColumns('post_translations', [
    'update_needed' => ['boolean', 'default' => false],
]);
