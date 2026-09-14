<?php

namespace Itqan\Discussions\Repository;

use Flarum\Discussion\Discussion;
use Flarum\Post\Post;
use Flarum\Settings\SettingsRepositoryInterface;
use Flarum\User\User;
use Illuminate\Database\Eloquent\Collection;

class ThreadRepository
{
    public const DEFAULT_ROOT_LIMIT = 20;
    public const DEFAULT_MAX_DEPTH = 4;
    public const DEFAULT_MAX_NODES_PER_ROOT = 20;

    /** @var SettingsRepositoryInterface */
    protected $settings;

    public function __construct(SettingsRepositoryInterface $settings)
    {
        $this->settings = $settings;
    }

    public function getMaxDepth(): int
    {
        return max(1, (int) $this->settings->get('itqan-discussions.maxDepth', self::DEFAULT_MAX_DEPTH));
    }

    public function getMaxNodesPerRoot(): int
    {
        return max(1, (int) $this->settings->get('itqan-discussions.maxNodesPerRoot', self::DEFAULT_MAX_NODES_PER_ROOT));
    }

    public function getRootCommentCount(Discussion $discussion, User $actor): int
    {
        return $discussion->posts()
            ->whereVisibleTo($actor)
            ->where('number', '>', 1)
            ->whereNull('parent_id')
            ->count();
    }

    /**
     * Load a paginated slice of root posts plus capped recursive descendants.
     *
     * @return array{
     *   root_ids: int[],
     *   all_post_ids: int[],
     *   posts: Collection,
     *   truncated_parent_ids: int[],
     *   roots_loaded: int,
     *   roots_has_more: bool,
     *   root_comment_count: int,
     *   sort: string
     * }
     */
    public function loadTreePosts(
        Discussion $discussion,
        User $actor,
        int $offset = 0,
        int $limit = self::DEFAULT_ROOT_LIMIT,
        string $sort = 'oldest',
        bool $includeOp = true,
        ?int $near = null
    ): array {
        $limit = max(1, min(50, $limit));
        $offset = max(0, $offset);
        $maxDepth = $this->getMaxDepth();
        $maxNodes = $this->getMaxNodesPerRoot();

        $opPost = null;
        if ($includeOp) {
            $opPost = $discussion->posts()->whereVisibleTo($actor)->where('number', 1)->first();
        }

        $rootCount = $this->getRootCommentCount($discussion, $actor);

        $rootsQuery = $discussion->posts()
            ->whereVisibleTo($actor)
            ->where('number', '>', 1)
            ->whereNull('parent_id');

        $this->applySort($rootsQuery, $sort);

        if ($near !== null && $near > 1) {
            $nearPost = $discussion->posts()->whereVisibleTo($actor)->where('number', $near)->first();
            if ($nearPost) {
                $targetRootId = (int) ($nearPost->root_id ?? $nearPost->parent_id ?? $nearPost->id);
                if ($nearPost->parent_id) {
                    $curr = $nearPost;
                    while ($curr && $curr->parent_id && (int) $curr->number > 1) {
                        $curr = Post::find($curr->parent_id);
                    }
                    if ($curr && (int) $curr->number > 1) {
                        $targetRootId = (int) $curr->id;
                    }
                }

                $allRootIds = (clone $rootsQuery)->pluck('id')->map(fn ($id) => (int) $id)->all();
                $pos = array_search($targetRootId, $allRootIds, true);
                if ($pos !== false) {
                    $offset = (int) (floor($pos / $limit) * $limit);
                }
            }
        }

        /** @var Collection $rootPosts */
        $rootPosts = $rootsQuery->skip($offset)->take($limit)->get();
        $rootIds = $rootPosts->pluck('id')->map(fn ($id) => (int) $id)->all();

        $orderedPosts = new Collection();
        if ($opPost) {
            $orderedPosts->push($opPost);
        }

        $truncatedParentIds = [];

        if (empty($rootIds)) {
            return [
                'root_ids' => [],
                'all_post_ids' => $orderedPosts->pluck('id')->all(),
                'posts' => $orderedPosts,
                'truncated_parent_ids' => [],
                'roots_loaded' => 0,
                'roots_has_more' => false,
                'root_comment_count' => $rootCount,
                'sort' => $sort,
            ];
        }

        // Prefetch all descendants under these roots (by root_id or parent walk)
        $allDescendants = $this->fetchDescendantsForRoots($discussion, $actor, $rootIds);
        $childrenByParent = [];
        foreach ($allDescendants as $child) {
            $pId = (int) $child->parent_id;
            if (! isset($childrenByParent[$pId])) {
                $childrenByParent[$pId] = [];
            }
            $childrenByParent[$pId][] = $child;
        }

        foreach ($childrenByParent as &$siblings) {
            usort($siblings, $this->siblingComparator($sort));
        }
        unset($siblings);

        foreach ($rootPosts as $root) {
            $nodesUsed = 0;
            $this->traverseCapped(
                $root,
                $childrenByParent,
                $orderedPosts,
                $truncatedParentIds,
                $nodesUsed,
                $maxNodes,
                $maxDepth,
                0
            );
        }

        return [
            'root_ids' => $rootIds,
            'all_post_ids' => $orderedPosts->pluck('id')->all(),
            'posts' => $orderedPosts,
            'truncated_parent_ids' => array_values(array_unique($truncatedParentIds)),
            'roots_loaded' => count($rootIds),
            'roots_has_more' => ($offset + count($rootIds)) < $rootCount,
            'root_comment_count' => $rootCount,
            'sort' => $sort,
        ];
    }

    /**
     * Load direct children of a post, each with a capped subtree.
     *
     * @return array{posts: Collection, truncated_parent_ids: int[], has_more: bool, total_children: int}
     */
    public function loadReplies(
        Post $parent,
        User $actor,
        int $offset = 0,
        int $limit = 20,
        string $sort = 'oldest'
    ): array {
        $discussion = $parent->discussion;
        $limit = max(1, min(50, $limit));
        $offset = max(0, $offset);
        $maxDepth = $this->getMaxDepth();
        $maxNodes = $this->getMaxNodesPerRoot();

        $totalChildren = $discussion->posts()
            ->whereVisibleTo($actor)
            ->where('parent_id', $parent->id)
            ->count();

        $childrenQuery = $discussion->posts()
            ->whereVisibleTo($actor)
            ->where('parent_id', $parent->id);

        $this->applySort($childrenQuery, $sort);

        /** @var Collection $children */
        $children = $childrenQuery->skip($offset)->take($limit)->get();

        $childIds = $children->pluck('id')->map(fn ($id) => (int) $id)->all();
        $descendants = empty($childIds)
            ? new Collection()
            : $this->fetchDescendantsForRoots($discussion, $actor, $childIds);

        // For expand-under-parent, treat each direct child as a "root" of its own capped subtree.
        // Also include descendants that hang under those children via parent_id.
        $allForMap = $descendants->merge($children);
        $childrenByParent = [];
        foreach ($allForMap as $node) {
            if (! $node->parent_id) {
                continue;
            }
            $pId = (int) $node->parent_id;
            if (! isset($childrenByParent[$pId])) {
                $childrenByParent[$pId] = [];
            }
            // Avoid duplicating the page children in the map from descendants
            $already = false;
            foreach ($childrenByParent[$pId] as $existing) {
                if ((int) $existing->id === (int) $node->id) {
                    $already = true;
                    break;
                }
            }
            if (! $already) {
                $childrenByParent[$pId][] = $node;
            }
        }
        foreach ($childrenByParent as &$siblings) {
            usort($siblings, $this->siblingComparator($sort));
        }
        unset($siblings);

        $orderedPosts = new Collection();
        $truncatedParentIds = [];

        // Parent depth is the baseline; children start at parent.depth + 1
        $parentDepth = (int) ($parent->depth ?? 0);

        foreach ($children as $child) {
            $nodesUsed = 0;
            // Relative depth budget: remaining depth from parent's perspective
            $remainingDepth = max(0, $maxDepth - ($parentDepth + 1));
            $this->traverseCapped(
                $child,
                $childrenByParent,
                $orderedPosts,
                $truncatedParentIds,
                $nodesUsed,
                $maxNodes,
                $remainingDepth,
                0
            );
        }

        return [
            'posts' => $orderedPosts,
            'truncated_parent_ids' => array_values(array_unique($truncatedParentIds)),
            'has_more' => ($offset + $children->count()) < $totalChildren,
            'total_children' => $totalChildren,
        ];
    }

    protected function fetchDescendantsForRoots(Discussion $discussion, User $actor, array $rootIds): Collection
    {
        if (empty($rootIds)) {
            return new Collection();
        }

        // Prefer root_id column when populated; also walk parent_id for safety during backfill gaps
        $byRootId = $discussion->posts()
            ->whereVisibleTo($actor)
            ->whereIn('root_id', $rootIds)
            ->get();

        $collected = new Collection();
        $seen = [];
        foreach ($byRootId as $post) {
            $seen[(int) $post->id] = true;
            $collected->push($post);
        }

        // Parent-walk for any children missing root_id
        $currentParentIds = $rootIds;
        $visitedParents = array_flip($rootIds);
        while (! empty($currentParentIds)) {
            $nextLevel = $discussion->posts()
                ->whereVisibleTo($actor)
                ->whereIn('parent_id', $currentParentIds)
                ->get();

            if ($nextLevel->isEmpty()) {
                break;
            }

            $newParentIds = [];
            foreach ($nextLevel as $child) {
                $id = (int) $child->id;
                if (! isset($seen[$id])) {
                    $seen[$id] = true;
                    $collected->push($child);
                }
                if (! isset($visitedParents[$id])) {
                    $visitedParents[$id] = true;
                    $newParentIds[] = $id;
                }
            }
            $currentParentIds = $newParentIds;
        }

        return $collected;
    }

    /**
     * @param array<int, Post[]> $childrenByParent
     * @param int[] $truncatedParentIds
     */
    protected function traverseCapped(
        Post $post,
        array &$childrenByParent,
        Collection $orderedPosts,
        array &$truncatedParentIds,
        int &$nodesUsed,
        int $maxNodes,
        int $maxDepth,
        int $relativeDepth
    ): void {
        $orderedPosts->push($post);
        $nodesUsed++;

        $pId = (int) $post->id;
        $children = $childrenByParent[$pId] ?? [];

        if (empty($children)) {
            return;
        }

        // At depth budget or node budget: mark truncated if any children remain
        if ($relativeDepth >= $maxDepth || $nodesUsed >= $maxNodes) {
            $truncatedParentIds[] = $pId;
            return;
        }

        foreach ($children as $child) {
            if ($nodesUsed >= $maxNodes) {
                $truncatedParentIds[] = $pId;
                break;
            }
            if ($relativeDepth + 1 > $maxDepth) {
                $truncatedParentIds[] = $pId;
                break;
            }

            $before = $nodesUsed;
            $this->traverseCapped(
                $child,
                $childrenByParent,
                $orderedPosts,
                $truncatedParentIds,
                $nodesUsed,
                $maxNodes,
                $maxDepth,
                $relativeDepth + 1
            );

            // If we couldn't take this child due to budget mid-siblings
            if ($nodesUsed === $before && $nodesUsed >= $maxNodes) {
                $truncatedParentIds[] = $pId;
                break;
            }
        }

        // If not all siblings were visited due to break, ensure truncated flag
        // (already handled above)
    }

    protected function applySort($query, string $sort): void
    {
        if ($sort === 'top') {
            $query->orderBy('votes', 'desc')->orderBy('created_at', 'asc');
        } elseif ($sort === 'latest') {
            $query->orderBy('created_at', 'desc');
        } else {
            $query->orderBy('created_at', 'asc');
        }
    }

    protected function siblingComparator(string $sort): callable
    {
        return function ($a, $b) use ($sort) {
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
    }

    public static function normalizeSort(?string $sort): string
    {
        $sort = (string) $sort;
        if (strpos($sort, 'top') !== false || strpos($sort, 'votes') !== false) {
            return 'top';
        }
        if (strpos($sort, 'latest') !== false || strpos($sort, 'newest') !== false || strpos($sort, '-created_at') !== false) {
            return 'latest';
        }
        return 'oldest';
    }
}
