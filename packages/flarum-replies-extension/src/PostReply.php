<?php

namespace Mtareq\NestedReplies;

use Flarum\Database\AbstractModel;
use Flarum\Post\Post;

class PostReply extends AbstractModel
{
    protected $table = 'mtareq_nested_replies_parents';

    protected $fillable = ['post_id', 'parent_post_id'];

    public $timestamps = true;

    /**
     * Cache of descendant counts per discussion, keyed by discussion id.
     *
     * @var array<int, array<int, int>>
     */
    protected static $countCache = [];

    /**
     * Number of descendant replies for every post in a discussion.
     *
     * @param int $discussionId
     * @return array<int, int> post id => descendant count
     */
    public static function subtreeCounts($discussionId)
    {
        $discussionId = (int) $discussionId;

        if (isset(static::$countCache[$discussionId])) {
            return static::$countCache[$discussionId];
        }

        $postIds = Post::query()
            ->where('discussion_id', $discussionId)
            ->pluck('id')
            ->all();

        $children = [];

        if ($postIds) {
            $links = static::query()
                ->whereIn('post_id', $postIds)
                ->get(['post_id', 'parent_post_id']);

            foreach ($links as $link) {
                $children[(int) $link->parent_post_id][] = (int) $link->post_id;
            }
        }

        $counts = [];
        $visiting = [];

        $count = function ($id) use (&$count, &$children, &$counts, &$visiting) {
            if (isset($counts[$id])) {
                return $counts[$id];
            }

            if (isset($visiting[$id])) {
                return 0;
            }

            $visiting[$id] = true;

            $total = 0;
            foreach ($children[$id] ?? [] as $child) {
                $total += 1 + $count($child);
            }

            unset($visiting[$id]);

            return $counts[$id] = $total;
        };

        foreach ($postIds as $id) {
            $count((int) $id);
        }

        return static::$countCache[$discussionId] = $counts;
    }

    /**
     * Number of descendant replies for one post.
     *
     * @param int $discussionId
     * @param int $postId
     * @return int
     */
    public static function subtreeCount($discussionId, $postId)
    {
        $counts = static::subtreeCounts($discussionId);

        return $counts[(int) $postId] ?? 0;
    }

    public function post()
    {
        return $this->belongsTo(Post::class, 'post_id');
    }

    public function parent()
    {
        return $this->belongsTo(Post::class, 'parent_post_id');
    }
}
