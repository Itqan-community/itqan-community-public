<?php

namespace Itqan\Discussions\Repository;

use Flarum\Discussion\Discussion;
use Flarum\Post\Post;
use Flarum\User\User;
use Illuminate\Database\Eloquent\Collection;

class ThreadRepository
{
    /**
     * Get root posts count for a discussion visible to the actor.
     */
    public function getRootCommentCount(Discussion $discussion, User $actor): int
    {
        return $discussion->posts()
            ->whereVisibleTo($actor)
            ->where('number', '>', 1)
            ->whereNull('parent_id')
            ->count();
    }

    /**
     * Load a paginated slice of root posts plus all their recursive descendants.
     *
     * @param Discussion $discussion
     * @param User $actor
     * @param int $offset
     * @param int $limit
     * @param string $sort 'oldest', 'latest', or 'top'
     * @return array Array containing ['root_ids' => [...], 'all_post_ids' => [...], 'posts' => Collection]
     */
    public function loadTreePosts(
        Discussion $discussion,
        User $actor,
        int $offset = 0,
        int $limit = 300,
        string $sort = 'oldest'
    ): array {
        // 1. Always load Post #1 (OP) first
        $opPost = $discussion->posts()->whereVisibleTo($actor)->where('number', 1)->first();

        // 2. Query root posts for this discussion
        $rootsQuery = $discussion->posts()
            ->whereVisibleTo($actor)
            ->where('number', '>', 1)
            ->whereNull('parent_id');

        // Apply sorting
        if ($sort === 'top') {
            $rootsQuery->orderBy('votes', 'desc')->orderBy('created_at', 'asc');
        } elseif ($sort === 'latest') {
            $rootsQuery->orderBy('created_at', 'desc');
        } else {
            // Default: oldest
            $rootsQuery->orderBy('created_at', 'asc');
        }

        /** @var Collection $rootPosts */
        $rootPosts = $rootsQuery->skip($offset)->take($limit)->get();
        $rootIds = $rootPosts->pluck('id')->all();

        if (empty($rootIds)) {
            $allPosts = $opPost ? new Collection([$opPost]) : new Collection();
            return [
                'root_ids' => [],
                'all_post_ids' => $opPost ? [$opPost->id] : [],
                'posts' => $allPosts
            ];
        }

        // 3. Eager load all recursive descendants for these root posts
        $descendants = new Collection();
        $currentParentIds = $rootIds;
        $visitedIds = array_flip($rootIds);

        while (!empty($currentParentIds)) {
            /** @var Collection $nextLevel */
            $nextLevel = $discussion->posts()
                ->whereVisibleTo($actor)
                ->whereIn('parent_id', $currentParentIds)
                ->get();

            if ($nextLevel->isEmpty()) {
                break;
            }

            $newParentIds = [];
            foreach ($nextLevel as $child) {
                if (!isset($visitedIds[$child->id])) {
                    $visitedIds[$child->id] = true;
                    $descendants->push($child);
                    $newParentIds[] = $child->id;
                }
            }

            $currentParentIds = $newParentIds;
        }

        // 4. Group children by parent_id
        $childrenByParent = [];
        foreach ($descendants as $child) {
            $pId = (int) $child->parent_id;
            if (!isset($childrenByParent[$pId])) {
                $childrenByParent[$pId] = [];
            }
            $childrenByParent[$pId][] = $child;
        }

        // Comparator for sibling sorting
        $siblingSorter = function ($a, $b) use ($sort) {
            if ($sort === 'top') {
                $votesA = (int) ($a->votes ?? 0);
                $votesB = (int) ($b->votes ?? 0);
                if ($votesB !== $votesA) {
                    return $votesB - $votesA;
                }
            } elseif ($sort === 'latest') {
                $timeA = $a->created_at ? $a->created_at->timestamp : 0;
                $timeB = $b->created_at ? $b->created_at->timestamp : 0;
                if ($timeB !== $timeA) {
                    return $timeB - $timeA;
                }
            }
            $timeA = $a->created_at ? $a->created_at->timestamp : 0;
            $timeB = $b->created_at ? $b->created_at->timestamp : 0;
            return $timeA - $timeB;
        };

        // 5. Build depth-first hierarchical sequence
        $orderedPosts = new Collection();
        if ($opPost) {
            $orderedPosts->push($opPost);
        }

        $traverse = function ($post) use (&$traverse, &$orderedPosts, &$childrenByParent, $siblingSorter) {
            $orderedPosts->push($post);
            $pId = (int) $post->id;
            if (isset($childrenByParent[$pId])) {
                $children = $childrenByParent[$pId];
                usort($children, $siblingSorter);
                foreach ($children as $child) {
                    $traverse($child);
                }
            }
        };

        foreach ($rootPosts as $root) {
            $traverse($root);
        }

        return [
            'root_ids' => $rootIds,
            'all_post_ids' => $orderedPosts->pluck('id')->all(),
            'posts' => $orderedPosts
        ];
    }
}
