<?php

use Carbon\Carbon;
use Illuminate\Database\Schema\Builder;
use Itqan\Notifications\Strings\NotificationStringCorrections;

return [
    'up' => function (Builder $schema) {
        $db = $schema->getConnection();
        $now = Carbon::now();

        foreach (NotificationStringCorrections::all() as $row) {
            $query = $db->table('fof_linguist_strings')
                ->where('key', $row['key'])
                ->where('locale', $row['locale']);

            if ($query->exists()) {
                $query->update(['value' => $row['value'], 'updated_at' => $now]);
            } else {
                $db->table('fof_linguist_strings')->insert([
                    'key' => $row['key'],
                    'locale' => $row['locale'],
                    'value' => $row['value'],
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    },
    'down' => function (Builder $schema) {
        $db = $schema->getConnection();

        foreach (NotificationStringCorrections::all() as $row) {
            $db->table('fof_linguist_strings')
                ->where('key', $row['key'])
                ->where('locale', $row['locale'])
                ->where('value', $row['value'])
                ->delete();
        }
    },
];
