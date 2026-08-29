<?php

namespace Itqan\Discussions\Api;

use Flarum\Api\Controller\AbstractShowController;
use Flarum\Api\Serializer\PostSerializer;
use Flarum\Http\RequestUtil;
use Flarum\Post\PostRepository;
use Illuminate\Support\Arr;
use Itqan\Discussions\Vote\Vote;
use Itqan\Discussions\Vote\VoteRepository;
use Psr\Http\Message\ServerRequestInterface;
use Tobscure\JsonApi\Document;

/**
 * PATCH /api/posts/{id}/vote
 *
 * Body: { "data": { "attributes": { "vote": 1 | 0 | -1 } } }
 *
 * Returns the post, so the client can replace its optimistic guess with the
 * authoritative score rather than trusting its own arithmetic.
 */
class VoteController extends AbstractShowController
{
    public $serializer = PostSerializer::class;

    protected $posts;
    protected $votes;

    public function __construct(PostRepository $posts, VoteRepository $votes)
    {
        $this->posts = $posts;
        $this->votes = $votes;
    }

    protected function data(ServerRequestInterface $request, Document $document)
    {
        $actor = RequestUtil::getActor($request);
        $actor->assertRegistered();

        $post = $this->posts->findOrFail(Arr::get($request->getQueryParams(), 'id'), $actor);

        $actor->assertCan('vote', $post);

        $value = (int) Arr::get($request->getParsedBody(), 'data.attributes.vote', 0);

        // Anything that is not a vote is a withdrawal. Silently coercing keeps
        // a malformed client from writing nonsense into the table.
        if (! Vote::isValidValue($value)) {
            $value = 0;
        }

        return $this->votes->vote($post, $actor, $value);
    }
}
