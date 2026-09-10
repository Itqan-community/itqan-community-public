<?php

namespace Itqan\Discussions\Api;

use Flarum\Discussion\Discussion;
use Flarum\Http\RequestUtil;
use Flarum\Post\Post;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Arr;
use Itqan\Discussions\Repository\ThreadRepository;
use Psr\Http\Message\ServerRequestInterface;

class LoadTreePostsRelationship
{
    /**
     * @var ThreadRepository
     */
    protected $threads;

    public function __construct(ThreadRepository $threads)
    {
        $this->threads = $threads;
    }

    /**
     * Modify discussion posts relation before serialization in ShowDiscussionController.
     */
    public function __invoke($controller, $discussion, ServerRequestInterface $request): void
    {
        if (! ($discussion instanceof Discussion)) {
            return;
        }

        $actor = RequestUtil::getActor($request);
        $queryParams = $request->getQueryParams();

        // Check if tree pagination is requested (or default for discussions)
        $limit = (int) Arr::get($queryParams, 'page.limit', 300);
        $offset = (int) Arr::get($queryParams, 'page.offset', 0);
        $sort = (string) Arr::get($queryParams, 'sort', 'oldest');

        // Extract sort preference
        if (strpos($sort, 'top') !== false || strpos($sort, 'votes') !== false) {
            $sortMode = 'top';
        } elseif (strpos($sort, 'latest') !== false || strpos($sort, 'newest') !== false || strpos($sort, '-created_at') !== false) {
            $sortMode = 'latest';
        } else {
            $sortMode = 'oldest';
        }

        // Calculate root comment count
        $rootCount = $this->threads->getRootCommentCount($discussion, $actor);
        $discussion->root_comment_count = $rootCount;

        // Load tree posts
        $treeData = $this->threads->loadTreePosts($discussion, $actor, $offset, $limit, $sortMode);
        $treePosts = $treeData['posts'];

        // Get full post IDs for the discussion (ordered: OP, then root tree posts, then any remaining posts)
        $allPostIds = $discussion->posts()->whereVisibleTo($actor)->pluck('id')->all();

        // Put tree posts at the start of allPostIds while preserving remaining IDs
        $treePostIds = $treePosts->pluck('id')->all();
        $remainingIds = array_values(array_diff($allPostIds, $treePostIds));
        $combinedIds = array_merge($treePostIds, $remainingIds);

        // Replace posts relation with loaded tree posts followed by unloaded post IDs
        // Flarum's ShowDiscussionController expects loaded Post models for the current page
        // and ID strings/integers for unloaded posts in the 'posts' relation.
        $finalPostsRelation = array_merge($treePosts->all(), $remainingIds);

        $discussion->setRelation('posts', $finalPostsRelation);
    }
}
