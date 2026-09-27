<?php

use Illuminate\Database\Schema\Builder;

/*
 * One-time (but permanently re-runnable) bridge from itqan-discussions' stores
 * to ours, executed at enable time while itqan's tables still exist — Flarum
 * never runs rollbacks on *disable*, so sources are guaranteed present.
 *
 * 1. post_votes -> mtareq_nested_replies_votes, INSERT IGNORE on our (post,user)
 *    unique key: re-runs and partial histories can never duplicate or abort.
 * 2. posts.parent_id -> mtareq_nested_replies_parents. posts.parent_id has no
 *    FK, so rows pointing at deleted parents (or themselves) are filtered out.
 * 3. Seed discussions.votes FROM ROWS — never ±1 deltas.
 */

return [
    'up' => function (Builder $schema) {
        $conn = $schema->getConnection();

        // 1) Votes copy -----------------------------------------------------
        if ($schema->hasTable('post_votes') && $schema->hasTable('mtareq_nested_replies_votes')) {
            $rows = $conn->table('post_votes')
                ->get(['post_id', 'user_id', 'value', 'created_at'])
                ->map(fn ($r) => [
                    'post_id' => $r->post_id,
                    'user_id' => $r->user_id,
                    'value' => $r->value,
                    'created_at' => $r->created_at,
                    'updated_at' => $r->created_at,
                ])->all();

            foreach (array_chunk($rows, 500) as $chunk) {
                $conn->table('mtareq_nested_replies_votes')->insertOrIgnore($chunk);
            }
        }

        // 2) Parent-link copy ----------------------------------------------
        if ($schema->hasColumn('posts', 'parent_id') && $schema->hasTable('mtareq_nested_replies_parents')) {
            $rows = $conn->table('posts as p')
                ->join('posts as par', 'par.id', '=', 'p.parent_id')
                ->whereNotNull('p.parent_id')
                ->whereColumn('p.id', '!=', 'p.parent_id')
                ->get(['p.id as post_id', 'p.parent_id'])
                ->map(fn ($r) => ['post_id' => $r->post_id, 'parent_post_id' => $r->parent_id])
                ->all();

            foreach (array_chunk($rows, 500) as $chunk) {
                $conn->table('mtareq_nested_replies_parents')->insertOrIgnore($chunk);
            }
        }

        // 3) Seed discussion scores from rows --------------------------------
        if ($schema->hasColumn('discussions', 'votes') && $schema->hasTable('mtareq_nested_replies_votes')) {
            $conn->table('discussions')
                ->select('id', 'first_post_id')
                ->orderBy('id')
                ->chunk(200, function ($discussions) use ($conn) {
                    // chunk() passes a Collection, not an array.
                    $firstPostIds = $discussions->pluck('first_post_id')->all();

                    $sums = $conn->table('mtareq_nested_replies_votes')
                        ->whereIn('post_id', $firstPostIds)
                        ->groupBy('post_id')
                        ->selectRaw('post_id, COALESCE(SUM(value), 0) as total')
                        ->get()
                        ->pluck('total', 'post_id');

                    foreach ($discussions as $d) {
                        $conn->table('discussions')->where('id', $d->id)->update([
                            'votes' => (int) ($sums[$d->first_post_id] ?? 0),
                        ]);
                    }
                });
        }
    },

    'down' => function (Builder $schema) {
        // Data migration: additive + idempotent, nothing to reverse.
        // Column removal lives in 2026_09_28_000000's down().
    },
];
