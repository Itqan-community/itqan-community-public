<?php

use Flarum\Extend;
use Itqan\PrettyMail\Renderer;

return [
    (new Extend\Frontend('admin'))
        ->js(__DIR__.'/js/dist/admin.js'),

    new Extend\Locales(__DIR__.'/locale'),

    // Core registers `mail::` from `views/email`; prepending our directory
    // shadows only `html` and `html.notification`, everything else falls
    // through to core.
    (new Extend\View())
        ->extendNamespace('mail', __DIR__.'/views'),

    (new Extend\Settings())
        ->default('fof-pretty-mail.includeCSS', '0'),
];
