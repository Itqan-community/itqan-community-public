<?php

use Flarum\Extend;
use Itqan\Notifications\Provider\ResolveStringsServiceProvider;

return [
    (new Extend\Frontend('forum'))
        ->js(__DIR__.'/js/dist/forum.js'),

    new Extend\Locales(__DIR__.'/locale'),

    (new Extend\User())
        ->registerPreference('dndEnabled', 'boolval', false),

    // W1 — notification content corrections (push-title keys + linguist string fixes).
    (new Extend\ServiceProvider())
        ->register(ResolveStringsServiceProvider::class),
];
