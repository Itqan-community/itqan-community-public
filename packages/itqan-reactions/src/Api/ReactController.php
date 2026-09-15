<?php

namespace Itqan\Reactions\Api;

use Flarum\Api\Controller\AbstractShowController;
use Flarum\Api\Serializer\PostSerializer;
use Flarum\Http\RequestUtil;
use Flarum\Post\PostRepository;
use Illuminate\Support\Arr;
use Itqan\Reactions\ReactionRepository;
use Psr\Http\Message\ServerRequestInterface;
use Tobscure\JsonApi\Document;

/**
 * POST /api/posts/{id}/reactions
 * Body: { "data": { "attributes": { "reaction": "heart" } } }
 */
class ReactController extends AbstractShowController
{
    public $serializer = PostSerializer::class;

    public $include = ['user', 'discussion'];

    /** @var PostRepository */
    protected $posts;

    /** @var ReactionRepository */
    protected $reactions;

    public function __construct(PostRepository $posts, ReactionRepository $reactions)
    {
        $this->posts = $posts;
        $this->reactions = $reactions;
    }

    protected function data(ServerRequestInterface $request, Document $document)
    {
        $actor = RequestUtil::getActor($request);
        $actor->assertRegistered();

        $post = $this->posts->findOrFail(Arr::get($request->getQueryParams(), 'id'), $actor);
        $actor->assertCan('react', $post);

        $identifier = (string) (
            Arr::get($request->getParsedBody(), 'data.attributes.reaction')
            ?? Arr::get($request->getParsedBody(), 'reaction')
            ?? ''
        );

        if ($identifier === '') {
            throw new \InvalidArgumentException('reaction identifier is required');
        }

        return $this->reactions->toggle($post, $actor, $identifier);
    }
}
