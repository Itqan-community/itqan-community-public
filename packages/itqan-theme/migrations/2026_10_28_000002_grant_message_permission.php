<?php

use Illuminate\Database\Schema\Builder;

/**
 * Flarum Messages only lets a member start a conversation with someone whose
 * groups can reply. The extension seeds `dialog.sendMessage` for the Member
 * group only, so members could not message admins, moderators or staff — the
 * profile button showed "cannot reply" and did nothing.
 *
 * Grant it to every non-guest group. Idempotent.
 */
return [
    'up' => function (Builder $schema) {
        $db = $schema->getConnection();

        $groupIds = $db->table('groups')
            ->where('id', '<>', 2) // Guest
            ->pluck('id');

        foreach ($groupIds as $groupId) {
            $db->table('group_permission')->insertOrIgnore([
                'group_id' => $groupId,
                'permission' => 'dialog.sendMessage',
            ]);
        }
    },

    'down' => function (Builder $schema) {
        //
    },
];
