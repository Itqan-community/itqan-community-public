<?php

use Illuminate\Database\Schema\Builder;

/**
 * Flarum 2's translator renders HTML in a translation as a Mithril element, and
 * an ICU `#` inside that HTML is no longer substituted: production's Linguist
 * overrides for the badge counts contain `<b># …</b>` and render as
 * "[object Object]" on 2.0.
 *
 * Dropping those overrides lets the extension's own locale (which now uses the
 * count without wrapping it in a tag) take over. Other badge strings — the
 * names and descriptions the community wrote — are left alone.
 */
return [
    'up' => function (Builder $schema) {
        $connection = $schema->getConnection();

        if (! $connection->getSchemaBuilder()->hasTable('fof_linguist_strings')) {
            return;
        }

        $connection->table('fof_linguist_strings')
            ->where('key', 'like', 'v17development-flarum-badges.%')
            ->where('value', 'like', '%<b>#%')
            ->delete();
    },
    'down' => function (Builder $schema) {
        // The previous strings rendered a literal "[object Object]" on 2.0;
        // restoring them would be a regression, so there is nothing to do.
    },
];
