<?php

use Flarum\Extend;
use Itqan\LangArabic\ArabicPriorityProvider;

// The package ships no PSR-4 autoload entry on purpose: adding one would change
// composer.json, and the deploy only runs `composer install` against the
// committed lock. The provider is a single class, so it is included directly.
require_once __DIR__.'/src/ArabicPriorityProvider.php';

return [
    new Flarum\Extend\LanguagePack(),

    (new Extend\ServiceProvider())
        ->register(ArabicPriorityProvider::class),
];
