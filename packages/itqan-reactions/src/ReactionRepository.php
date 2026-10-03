<?php

namespace Itqan\Reactions;

use Flarum\Post\Post;
use Flarum\User\User;
use Illuminate\Database\ConnectionInterface;

class ReactionRepository
{
    /** @var ConnectionInterface */
    protected $db;

    public function __construct(ConnectionInterface $db)
    {
        $this->db = $db;
    }

    /**
     * Toggle a reaction for the actor on a post (GitHub-style: one active type).
     */
    public function toggle(Post $post, User $actor, string $identifier): Post
    {
        $type = ReactionType::enabled()->where('identifier', $identifier)->firstOrFail();

        return $this->db->transaction(function () use ($post, $actor, $type) {
            $existingSame = Reaction::where('post_id', $post->id)
                ->where('user_id', $actor->id)
                ->where('reaction_id', $type->id)
                ->first();

            if ($existingSame) {
                $existingSame->delete();
            } else {
                Reaction::where('post_id', $post->id)
                    ->where('user_id', $actor->id)
                    ->delete();

                $reaction = new Reaction();
                $reaction->post_id = $post->id;
                $reaction->user_id = $actor->id;
                $reaction->reaction_id = $type->id;
                $reaction->save();
            }

            $this->refreshCounts($post);

            return $post->fresh(['itqanReactions']);
        });
    }

    public function refreshCounts(Post $post): void
    {
        $rows = Reaction::query()
            ->where('post_id', $post->id)
            ->selectRaw('reaction_id, COUNT(*) as c')
            ->groupBy('reaction_id')
            ->pluck('c', 'reaction_id');

        $types = ReactionType::enabled()->get()->keyBy('id');
        $counts = [];
        foreach ($rows as $reactionId => $count) {
            $type = $types->get($reactionId);
            if ($type) {
                $counts[$type->identifier] = (int) $count;
            }
        }

        $post->reaction_counts = ! empty($counts) ? json_encode($counts) : null;
        $post->save();
    }

    /**
     * Build summary for serializer.
     *
     * @return array<int, array{identifier: string, emoji: string, count: int, me: bool}>
     */
    public function summaryFor(Post $post, ?User $actor): array
    {
        $counts = $post->reaction_counts;
        if (is_string($counts)) {
            $counts = json_decode($counts, true) ?: [];
        }
        if (! is_array($counts)) {
            $counts = [];
        }

        $myIds = [];
        if ($actor && $actor->exists) {
            // Prefer eager-loaded relation when present
            if ($post->relationLoaded('itqanReactions')) {
                foreach ($post->itqanReactions as $reaction) {
                    if ((int) $reaction->user_id === (int) $actor->id) {
                        $myIds[] = (int) $reaction->reaction_id;
                    }
                }
            } else {
                $myIds = Reaction::where('post_id', $post->id)
                    ->where('user_id', $actor->id)
                    ->pluck('reaction_id')
                    ->map(fn ($id) => (int) $id)
                    ->all();
            }
        }

        $types = ReactionType::enabled()->orderBy('position')->get();
        $summary = [];
        foreach ($types as $type) {
            $count = (int) ($counts[$type->identifier] ?? 0);
            if ($count <= 0 && ! in_array((int) $type->id, $myIds, true)) {
                continue; // only show types with activity (picker lists all)
            }
            $summary[] = [
                'identifier' => $type->identifier,
                'emoji' => $type->emoji,
                'count' => $count,
                'me' => in_array((int) $type->id, $myIds, true),
            ];
        }

        return $summary;
    }
}
