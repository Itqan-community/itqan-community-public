<?php

namespace Itqan\Llms\Markdown;

use Flarum\Post\Post;

/**
 * Rebuilds the reply tree from `posts.parent_id`.
 *
 * itqan/flarum-discussions stores the parent of every nested reply in
 * `parent_id` and keeps `reply_count` in step. Reading a thread in `number`
 * order — which is what a plain chronological export does — destroys that
 * structure: a reply to post #2 that was written after post #50 sorts at #50,
 * so a conversation arrives interleaved with unrelated comments and the model
 * cannot tell who was answering whom.
 *
 * This walks the tree depth-first instead, so each reply is emitted directly
 * beneath the comment it answers, and reports the depth so the renderer can
 * make the nesting visible.
 */
class ThreadTree
{
    /**
     * Guards against a cycle if bad data ever puts a post beneath itself. A
     * runaway export on a public URL is a worse failure than a flattened one.
     */
    private const MAX_DEPTH = 64;

    /**
     * Build the tree, returning the root nodes.
     *
     * @param Post[] $posts Visible posts, in any order.
     * @return ThreadNode[]
     */
    public function build(array $posts): array
    {
        /** @var array<int, ThreadNode> $nodes */
        $nodes = [];

        foreach ($posts as $post) {
            $nodes[(int) $post->id] = new ThreadNode($post);
        }

        /** @var array<int, ThreadNode[]> $childrenOf */
        $childrenOf = [];
        $roots = [];

        foreach ($posts as $post) {
            $node = $nodes[(int) $post->id];
            $parentId = (int) ($post->parent_id ?? 0);

            // A parent the reader cannot see, or one outside this batch, means
            // the reply has no addressable ancestor here and is treated as a
            // root. Silently dropping it would lose a comment. So would
            // nesting a post beneath an ancestor chain that leads back to it.
            if ($parentId > 0
                && $parentId !== (int) $post->id
                && isset($nodes[$parentId])
                && ! $this->climbsBackTo($parentId, (int) $post->id, $nodes)
            ) {
                $childrenOf[$parentId][] = $node;
            } else {
                $roots[] = $node;
            }
        }

        // Siblings keep chronological order, so the tree reads in the order the
        // conversation actually happened.
        foreach ($childrenOf as $parentId => $siblings) {
            usort($siblings, fn (ThreadNode $a, ThreadNode $b): int => $this->compareNumbers($a, $b));
            $nodes[$parentId]->children = $siblings;
        }

        usort($roots, fn (ThreadNode $a, ThreadNode $b): int => $this->compareNumbers($a, $b));

        // `number` is unique per discussion, so the opening post (number 1) is
        // first and the rest of the roots are the top-level replies.
        return $roots;
    }

    /**
     * Whether walking up from $startId reaches $targetId.
     *
     * `parent_id` has no foreign key, so bad data can point two posts at each
     * other. Nesting under such a parent would make a post its own ancestor and
     * recurse forever; catching it here keeps the reply, as a root, instead.
     *
     * @param array<int, ThreadNode> $nodes
     */
    private function climbsBackTo(int $startId, int $targetId, array $nodes): bool
    {
        $seen = [];
        $current = $startId;

        while (isset($nodes[$current]) && ! isset($seen[$current])) {
            if ($current === $targetId) {
                return true;
            }

            $seen[$current] = true;
            $current = (int) ($nodes[$current]->post->parent_id ?? 0);
        }

        return false;
    }

    /**
     * Flatten the tree into the order the document should be written in,
     * carrying each node's depth.
     *
     * @param ThreadNode[] $roots
     * @return ThreadNode[]
     */
    public function flatten(array $roots): array
    {
        $out = [];
        $this->walk($roots, $out);

        return $out;
    }

    /**
     * @param ThreadNode[] $nodes
     * @param ThreadNode[] $out
     */
    private function walk(array $nodes, array &$out, int $depth = 0): void
    {
        foreach ($nodes as $node) {
            $node->depth = $depth;
            $out[] = $node;

            if ($node->children === []) {
                continue;
            }

            if ($depth >= self::MAX_DEPTH) {
                // A chain this deep means the stored data is pathological.
                // Every post is still emitted — dropping the tail would lose
                // comments — but the rest of the subtree is held at this depth
                // instead of nesting further, which also keeps the recursion
                // bounded.
                $this->walkUnnested($node->children, $out, $depth);
                continue;
            }

            $this->walk($node->children, $out, $depth + 1);
        }
    }

    /**
     * Depth-first over an explicit stack, so a runaway chain cannot exhaust
     * the PHP call stack either.
     *
     * @param ThreadNode[] $nodes
     * @param ThreadNode[] $out
     */
    private function walkUnnested(array $nodes, array &$out, int $depth): void
    {
        $stack = array_reverse($nodes);

        while ($stack) {
            $node = array_pop($stack);
            $node->depth = $depth;
            $out[] = $node;

            foreach (array_reverse($node->children) as $child) {
                $stack[] = $child;
            }
        }
    }

    private function compareNumbers(ThreadNode $a, ThreadNode $b): int
    {
        return ((int) $a->post->number) <=> ((int) $b->post->number);
    }
}
