<?php

use Illuminate\Database\Schema\Builder;

/**
 * Restores discussion view counts on Flarum 2.0.
 *
 * `michaelbelgium/flarum-discussion-views` 8.x gates `viewCount` behind the
 * `readViewnumber` / `readViewlist` abilities, and Flarum's discussion policy
 * resolves those through the per-tag permission check. Production never had
 * these rows because 1.8 serialized the count unconditionally, so the count
 * vanished on 2.0 until they were granted.
 *
 * Idempotent: re-running is a no-op.
 */
return [
    'up' => function (Builder $schema) {
        $db = $schema->getConnection();

        $grants = [];

        foreach (['readViewnumber', 'readViewlist'] as $ability) {
            $grants[] = ['permission' => 'discussion.'.$ability, 'scope' => 'discussion.readViewlist'];
        }

        foreach ($grants as $grant) {
            $groupIds = $db->table('group_permission')
                ->where('permission', $grant['scope'])
                ->pluck('group_id')
                ->unique();

            foreach ($groupIds as $groupId) {
                $db->table('group_permission')->insertOrIgnore([
                    'group_id' => $groupId,
                    'permission' => $grant['permission'],
                ]);
            }
        }

        // Restricted tags deny every discussion ability unless the actor holds
        // the matching per-tag permission, so mirror the permissions that the
        // tag already grants for discussions.
        $restrictedTags = $db->table('tags')->where('is_restricted', 1)->pluck('id');

        foreach ($restrictedTags as $tagId) {
            $groupIds = $db->table('group_permission')
                ->where('permission', 'like', 'tag'.$tagId.'.discussion.%')
                ->pluck('group_id')
                ->merge(
                    $db->table('group_permission')
                        ->where('permission', 'tag'.$tagId.'.viewForum')
                        ->pluck('group_id')
                )
                ->unique();

            foreach (['readViewnumber', 'readViewlist'] as $ability) {
                foreach ($groupIds as $groupId) {
                    $db->table('group_permission')->insertOrIgnore([
                        'group_id' => $groupId,
                        'permission' => 'tag'.$tagId.'.discussion.'.$ability,
                    ]);
                }
            }
        }
    },

    // Permissions are additive and harmless; there is no safe way to tell the
    // rows this migration added from ones an admin granted later.
    'down' => function (Builder $schema) {
        //
    },
];
