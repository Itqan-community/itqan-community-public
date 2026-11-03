<?php

use Flarum\Extend;
use Itqan\Theme\Content\Preconnect;

return [
    (new Extend\Frontend('forum'))
        ->js(__DIR__.'/js/dist/forum.js')
        ->css(__DIR__.'/less/forum.less')
        ->content(Preconnect::class),

    new Extend\Locales(__DIR__.'/locale'),
];
