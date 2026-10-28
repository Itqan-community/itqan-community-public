<?php

use Flarum\Settings\SettingsRepositoryInterface;
use Illuminate\Database\Schema\Builder;

/**
 * Seed the branded templates that production already uses.
 *
 * The 1.x `fof/pretty-mail` settings keys are kept so a database that already
 * has them (production does) needs no migration and keeps its exact emails.
 * This migration only fills the gaps on a fresh install.
 */
return [
    'up' => function (Builder $schema) {
        /** @var SettingsRepositoryInterface $settings */
        $settings = resolve(SettingsRepositoryInterface::class);

        foreach (['mailhtml', 'newPost', 'postMentioned', 'userMentioned'] as $key) {
            $full = 'fof-pretty-mail.'.$key;

            if ((string) $settings->get($full, '') !== '') {
                continue;
            }

            $default = __DIR__.'/../resources/defaults/'.$key.'.blade.php';

            if (is_file($default)) {
                $settings->set($full, (string) file_get_contents($default));
            }
        }
    },

    'down' => function (Builder $schema) {
        //
    },
];
