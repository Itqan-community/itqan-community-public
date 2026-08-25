<?php

use Flarum\Extend;
use Itqan\Typography\Content\DropRemoteFontLinks;
use Itqan\Typography\Fonts;

return [
    (new Extend\Frontend('forum'))
        ->css(__DIR__.'/less/forum.less')
        // The browser cannot discover a font from inside a stylesheet until it
        // has fetched and parsed that stylesheet. On this forum the text font
        // was reaching the network only after the app had booted. Preloading
        // starts it with the page instead.
        ->preloads(function () {
            return Fonts::preloads();
        })
        ->content(DropRemoteFontLinks::class),

    // Absolute URL of the published font directory, so the @font-face rules do
    // not have to guess where assets live — the path is configurable.
    (new Extend\Theme())
        ->addCustomLessVariable('itqan-typography--fonts', function () {
            return '"'.Fonts::baseUrl().'"';
        }),
];
