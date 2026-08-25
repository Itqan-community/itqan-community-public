<?php

use Flarum\Extend;
use Itqan\Theme\Content\ApplyStoredScheme;

return [
    (new Extend\Frontend('forum'))
        ->js(__DIR__.'/js/dist/forum.js')
        ->css(__DIR__.'/less/forum.less')
        ->content(ApplyStoredScheme::class),

    (new Extend\Frontend('admin'))
        ->js(__DIR__.'/js/dist/admin.js'),

    new Extend\Locales(__DIR__.'/locale'),

    // The starting point for anyone who has never chosen. `auto` means the
    // forum takes its cue from the reader's operating system.
    (new Extend\Settings())
        ->default('itqan-theme.default_mode', 'auto')
        ->serializeToForum('itqanThemeDefault', 'itqan-theme.default_mode'),

    // Stored against the account rather than the browser, so a reader who signs
    // in on a second device finds the forum the way they left it.
    //
    // The default is deliberately null, not 'auto': it is how the frontend
    // tells "never chose" apart from "chose to follow the system", and only the
    // former should fall through to the forum-wide default.
    (new Extend\User())
        ->registerPreference('itqanTheme', function ($value) {
            return in_array($value, ApplyStoredScheme::MODES, true) ? $value : null;
        }, null),
];
