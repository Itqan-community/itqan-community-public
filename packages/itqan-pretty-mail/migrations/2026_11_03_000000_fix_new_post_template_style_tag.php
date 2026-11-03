<?php

use Flarum\Settings\SettingsRepositoryInterface;
use Illuminate\Database\Schema\Builder;

/**
 * The stored new-post template carried a stray "v" inside its <style> tag
 * (<style>v). It came from the legacy install and was copied into the default
 * template, so every environment that kept its old settings has it.
 *
 * Repair the stored value in place. The replace is a no-op when the template
 * is already clean, so it is safe on a fresh install.
 */
return [
    'up' => function (Builder $schema) {
        /** @var SettingsRepositoryInterface $settings */
        $settings = resolve(SettingsRepositoryInterface::class);

        $key = 'fof-pretty-mail.newPost';
        $template = (string) $settings->get($key, '');

        if ($template !== '' && str_contains($template, '<style>v')) {
            $settings->set($key, str_replace('<style>v', '<style>', $template));
        }
    },

    'down' => function (Builder $schema) {
        //
    },
];
