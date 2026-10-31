<?php

use Itqan\Translate\Text;
use Illuminate\Database\Schema\Builder;

/**
 * One-off cleanup of translations cached by the 1.8 extension.
 *
 * `ianm/translate` stored translations of the raw post body, which in Flarum is
 * s9e XML (`<r><p>…<POSTMENTION …>`). Those rows reach the UI as literal tags
 * ("<p>…</p>"). The controllers sanitise on read now; this scrubs the backlog so
 * the first request after the upgrade is clean too.
 */
return [
    'up' => function (Builder $schema) {
        $db = $schema->getConnection();

        foreach ([['post_translations', 'content'], ['discussion_translations', 'translation']] as [$table, $column]) {
            if (! $schema->hasTable($table)) {
                continue;
            }

            $db->table($table)
                ->select('id', $column)
                ->orderBy('id')
                ->chunk(200, function ($rows) use ($db, $table, $column) {
                    foreach ($rows as $row) {
                        $value = (string) $row->{$column};

                        if (! Text::hasMarkup($value)) {
                            continue;
                        }

                        $clean = Text::sanitize($value);

                        if ($clean !== $value) {
                            $db->table($table)->where('id', $row->id)->update([$column => $clean]);
                        }
                    }
                });
        }
    },

    'down' => function (Builder $schema) {
        // Not reversible: the original markup carried no meaning.
    },
];
