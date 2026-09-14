<?php

namespace Itqan\Discussions\Api;

use Flarum\Api\Controller\AbstractListController;
use Flarum\Api\Serializer\PostSerializer;
use Flarum\Discussion\DiscussionRepository;
use Flarum\Http\RequestUtil;
use Illuminate\Support\Arr;
use Itqan\Discussions\Repository\ThreadRepository;
use Psr\Http\Message\ServerRequestInterface;
use Tobscure\JsonApi\Document;

/**
 * GET /api/discussions/{id}/comment-tree
 *
 * Returns the next page of root comments with capped reply trees (no OP).
 */
class ListCommentTreeController extends AbstractListController
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

    /** @var DiscussionRepository */
    protected $discussions;

    /** @var ThreadRepository */
    protected $threads;

    public function __construct(DiscussionRepository $discussions, ThreadRepository $threads)
    {
        $this->discussions = $discussions;
        $this->threads = $threads;
    }

    protected function data(ServerRequestInterface $request, Document $document)
    {
        $actor = RequestUtil::getActor($request);
        $discussionId = Arr::get($request->getQueryParams(), 'id');
        $discussion = $this->discussions->findOrFail($discussionId, $actor);

        $queryParams = $request->getQueryParams();
        $limit = (int) Arr::get($queryParams, 'page.limit', ThreadRepository::DEFAULT_ROOT_LIMIT);
        $offset = (int) Arr::get($queryParams, 'page.offset', 0);
        $sort = ThreadRepository::normalizeSort(Arr::get($queryParams, 'sort', 'oldest'));
        $near = Arr::get($queryParams, 'near') ?? Arr::get($queryParams, 'page.near');
        $near = $near !== null ? (int) $near : null;

        $tree = $this->threads->loadTreePosts($discussion, $actor, $offset, $limit, $sort, false, $near);

        $document->setMeta([
            'rootCommentCount' => $tree['root_comment_count'],
            'rootsLoaded' => $tree['roots_loaded'],
            'rootsHasMore' => $tree['roots_has_more'],
            'sort' => $tree['sort'],
            'truncatedParentIds' => $tree['truncated_parent_ids'],
            'offset' => $offset,
            'limit' => $limit,
        ]);

        // Mark truncated parents on models for serializer
        $truncated = array_flip($tree['truncated_parent_ids']);
        foreach ($tree['posts'] as $post) {
            $post->setAttribute('has_more_replies', isset($truncated[(int) $post->id]));
        }

        return $tree['posts'];
    }
}
