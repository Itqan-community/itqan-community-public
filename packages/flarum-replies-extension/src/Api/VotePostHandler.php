<?php

namespace Mtareq\NestedReplies\Api;

use Flarum\Http\RequestUtil;
use Flarum\Post\PostRepository;
use Laminas\Diactoros\Response\EmptyResponse;
use Mtareq\NestedReplies\PostVote;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Version-agnostic vote endpoint. The Flarum 1.x AbstractShowController base
 * class was removed in 2.0; returning 204 keeps working because the vote
 * adapter applies the change optimistically and favours the server payload
 * only when one is present.
 */
class VotePostHandler implements RequestHandlerInterface
{
    public function __construct(protected PostRepository $posts)
    {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $actor = RequestUtil::getActor($request);
        $postId = (int) ($request->getAttribute('routeParameters')['id'] ?? 0);
        $body = (array) $request->getParsedBody();

        if ($postId <= 0) {
            return new EmptyResponse(400);
        }

        $direction = $body['direction'] ?? null;
        $value = $direction === 'up' ? 1 : ($direction === 'down' ? -1 : null);

        $post = $this->posts->findOrFail($postId, $actor);
        $actor->assertCan('vote', $post);

        $post->getConnection()->transaction(function () use ($post, $actor, $value) {
            if ($value === null) {
                PostVote::query()
                    ->where('post_id', $post->id)
                    ->where('user_id', $actor->id)
                    ->delete();
            } else {
                PostVote::query()->updateOrCreate(
                    ['post_id' => $post->id, 'user_id' => $actor->id],
                    ['value' => $value]
                );
            }
        });

        return new EmptyResponse(204);
    }
}
