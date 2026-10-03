<?php

namespace Itqan\Discussions\Api;

use Flarum\Discussion\Discussion;
use Flarum\Http\RequestUtil;
use Illuminate\Support\Arr;
use Itqan\Discussions\Repository\ThreadRepository;
use Psr\Http\Message\ServerRequestInterface;

class LoadTreePostsRelationship
{
    /** @var ThreadRepository */
    protected $threads;

    public function __construct(ThreadRepository $threads)
    {
        $this->threads = $threads;
    }

    /**
     * Replace discussion posts with OP + first page of capped root trees only.
     * Do NOT append remaining flat post IDs (that caused missing-reply / jump bugs).
     */
    public function __invoke($controller, $discussion, ServerRequestInterface $request): void
    {
        if (! ($discussion instanceof Discussion)) {
            return;
        }

        $actor = RequestUtil::getActor($request);
        $queryParams = $request->getQueryParams();

        $limit = (int) Arr::get($queryParams, 'page.limit', ThreadRepository::DEFAULT_ROOT_LIMIT);
        $offset = (int) Arr::get($queryParams, 'page.offset', 0);
        $sort = ThreadRepository::normalizeSort(Arr::get($queryParams, 'sort', 'oldest'));
        $near = Arr::get($queryParams, 'near') ?? Arr::get($queryParams, 'page.near');
        $near = $near !== null ? (int) $near : null;

        $tree = $this->threads->loadTreePosts($discussion, $actor, $offset, $limit, $sort, true, $near);

        $discussion->root_comment_count = $tree['root_comment_count'];
        $discussion->roots_loaded = $tree['roots_loaded'];
        $discussion->roots_has_more = $tree['roots_has_more'];
        $discussion->roots_has_previous = $tree['roots_has_previous'];
        $discussion->roots_offset = $tree['offset'];
        $discussion->comment_sort = $tree['sort'];
        $discussion->truncated_parent_ids = $tree['truncated_parent_ids'];

        $truncated = array_flip($tree['truncated_parent_ids']);
        foreach ($tree['posts'] as $post) {
            $post->setAttribute('has_more_replies', isset($truncated[(int) $post->id]));
        }

        // Only loaded models — no leftover ID stubs for scrubber windowing
        $discussion->setRelation('posts', $tree['posts']->all());
    }
}
