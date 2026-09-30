<?php

use Illuminate\Database\Schema\Builder;

return [
    'up' => function (Builder $schema) {
        $db = $schema->getConnection();

        $defaults = [
            ['identifier' => 'heart', 'emoji' => '❤️', 'label' => 'Heart', 'position' => 0],
            ['identifier' => 'thumbsup', 'emoji' => '👍', 'label' => 'Thumbs up', 'position' => 1],
            ['identifier' => 'joy', 'emoji' => '😂', 'label' => 'Joy', 'position' => 2],
            ['identifier' => 'tada', 'emoji' => '🎉', 'label' => 'Tada', 'position' => 3],
            ['identifier' => 'thinking', 'emoji' => '🤔', 'label' => 'Thinking', 'position' => 4],
            ['identifier' => 'eyes', 'emoji' => '👀', 'label' => 'Eyes', 'position' => 5],
        ];

        foreach ($defaults as $row) {
            $exists = $db->table('itqan_reaction_types')->where('identifier', $row['identifier'])->exists();
            if (! $exists) {
                $db->table('itqan_reaction_types')->insert(array_merge($row, ['enabled' => true]));
            }
        }

        if ($schema->hasTable('group_permission')) {
            $exists = $db->table('group_permission')
                ->where('group_id', 3)
                ->where('permission', 'discussion.itqanReact')
                ->exists();
            if (! $exists) {
                $db->table('group_permission')->insert([
                    'group_id' => 3,
                    'permission' => 'discussion.itqanReact',
                ]);
            }
        }
    },

    'down' => function (Builder $schema) {
        $db = $schema->getConnection();
        $db->table('itqan_reaction_types')->whereIn('identifier', [
            'heart', 'thumbsup', 'joy', 'tada', 'thinking', 'eyes',
        ])->delete();

        if ($schema->hasTable('group_permission')) {
            $db->table('group_permission')->where('permission', 'discussion.itqanReact')->delete();
        }
    },
];
