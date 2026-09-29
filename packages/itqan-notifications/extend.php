<?php

use Flarum\Extend;
use Itqan\Notifications\Provider\ResolveStringsServiceProvider;

return [
    new Extend\Locales(__DIR__.'/locale'),
    (new Extend\ServiceProvider())->register(ResolveStringsServiceProvider::class),
];
