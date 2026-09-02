<?php

use Illuminate\Database\Schema\Builder;
use Illuminate\Database\ConnectionInterface;

return [
    'up' => function (Builder $schema) {
        /** @var ConnectionInterface $db */
        $db = $schema->getConnection();

        // -------------------------------------------------------------
        // Safe Retroactive Backfill for Historical Posts
        // -------------------------------------------------------------
        $candidates = $db->table('posts')
            ->select('id', 'discussion_id', 'number', 'content')
            ->where('type', 'comment')
            ->whereNotNull('content')
            ->where('content', 'LIKE', '%POSTMENTION%')
            ->get();

        foreach ($candidates as $post) {
            // Guard: OP post #1 is never a child reply
            if ((int) $post->number <= 1) {
                continue;
            }

            if (preg_match('/<POSTMENTION\s+[^>]*id="(\d+)"/i', (string) $post->content, $matches)) {
                $parentId = (int) $matches[1];

                // Guard: cannot be its own parent
                if ($parentId > 0 && $parentId !== (int) $post->id) {
                    // Guard: verify parent exists and belongs to the exact same discussion
                    $parentExists = $db->table('posts')
                        ->where('id', $parentId)
                        ->where('discussion_id', $post->discussion_id)
                        ->exists();

                    if ($parentExists) {
                        $db->table('posts')
                            ->where('id', $post->id)
                            ->update(['parent_id' => $parentId]);
                    }
                }
            }
        }

        // Recalculate and synchronize reply_count for all parents
        $db->statement('
            UPDATE posts p
            LEFT JOIN (
                SELECT parent_id, COUNT(*) AS total
                FROM posts
                WHERE parent_id IS NOT NULL
                GROUP BY parent_id
            ) c ON p.id = c.parent_id
            SET p.reply_count = COALESCE(c.total, 0)
        ');
    },

    'down' => function (Builder $schema) {
        /** @var ConnectionInterface $db */
        $db = $schema->getConnection();

        // Reset parent_id and reply_count
        $db->table('posts')->update([
            'parent_id' => null,
            'reply_count' => 0,
        ]);
    }
];
