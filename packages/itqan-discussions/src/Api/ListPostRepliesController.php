<?php

namespace Itqan\Discussions\Api;

use Flarum\Api\Controller\AbstractListController;
use Flarum\Api\Serializer\PostSerializer;
use Flarum\Http\RequestUtil;
use Flarum\Post\PostRepository;
use Illuminate\Support\Arr;
use Itqan\Discussions\Repository\ThreadRepository;
use Psr\Http\Message\ServerRequestInterface;
use Tobscure\JsonApi\Document;

/**
 * GET /api/posts/{id}/replies
 *
 * Continue-this-thread: direct children of a post, each with a capped subtree.
 */
class ListPostRepliesController extends AbstractListController
{
    public $serializer = PostSerializer::class;

    public $include = [
        'user',
        'user.groups',
        'editedUser',
        'hiddenUser',
        'discussion',
        'postVotes',
    ];

    /** @var PostRepository */
    protected $posts;

    /** @var ThreadRepository */
    protected $threads;

    public function __construct(PostRepository $posts, ThreadRepository $threads)
    {
        $this->posts = $posts;
        $this->threads = $threads;
    }

    protected function data(ServerRequestInterface $request, Document $document)
    {
        $actor = RequestUtil::getActor($request);
        $postId = Arr::get($request->getQueryParams(), 'id');
        $parent = $this->posts->findOrFail($postId, $actor);

        $queryParams = $request->getQueryParams();
        $limit = (int) Arr::get($queryParams, 'page.limit', 20);
        $offset = (int) Arr::get($queryParams, 'page.offset', 0);
        $sort = ThreadRepository::normalizeSort(Arr::get($queryParams, 'sort', 'oldest'));

        $result = $this->threads->loadReplies($parent, $actor, $offset, $limit, $sort);

        $document->setMeta([
            'hasMore' => $result['has_more'],
            'totalChildren' => $result['total_children'],
            'truncatedParentIds' => $result['truncated_parent_ids'],
            'parentId' => (int) $parent->id,
            'sort' => $sort,
            'offset' => $offset,
            'limit' => $limit,
        ]);

        $truncated = array_flip($result['truncated_parent_ids']);
        foreach ($result['posts'] as $post) {
            $post->setAttribute('has_more_replies', isset($truncated[(int) $post->id]));
        }

        // Parent itself is no longer truncated for the loaded page of children
        // (client clears hasMoreReplies when expand completes without has_more)

        return $result['posts'];
    }
}
