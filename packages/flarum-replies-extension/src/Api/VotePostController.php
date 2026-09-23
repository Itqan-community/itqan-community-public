<?php

namespace Mtareq\NestedReplies\Api;

use Flarum\Api\Controller\AbstractShowController;
use Flarum\Api\Serializer\PostSerializer;
use Flarum\Http\RequestUtil;
use Flarum\Post\PostRepository;
use Illuminate\Support\Arr;
use Mtareq\NestedReplies\PostVote;
use Mtareq\NestedReplies\Vote\DiscussionScore;
use Mtareq\NestedReplies\Vote\VoteCounts;
use Psr\Http\Message\ServerRequestInterface;
use Tobscure\JsonApi\Document;

class VotePostController extends AbstractShowController
{
    /**
     * {@inheritdoc}
     */
    public $serializer = PostSerializer::class;

    /**
     * {@inheritdoc}
     */
    protected function data(ServerRequestInterface $request, Document $document)
    {
        $actor = RequestUtil::getActor($request);

        $id = Arr::get($request->getQueryParams(), 'id');

        // Repository (not Post::findOrFail) so scope visibility applies.
        /** @var \Flarum\Post\Post $post */
        $post = app(PostRepository::class)->findOrFail($id, $actor);

        // Own posts and invisible posts are denied by policy (spec §2), not by UI.
        $actor->assertCan('vote', $post);

        $body = $request->getParsedBody();
        $direction = is_array($body) ? Arr::get($body, 'direction') : null;

        $value = $direction === 'up' ? 1 : ($direction === 'down' ? -1 : null);

        // The model's own connection, not the DB facade: Flarum 1.8 never
        // sets a facade root, so Facade::__callStatic throws at runtime.
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

            // Score follows the write inside the same transaction; clear() so
            // the recompute SUM isn't served from a stale memo.
            VoteCounts::clear();
            DiscussionScore::recompute($post->discussion);
        });

        // Serialize the fresh state (vote row + scores changed).
        $post->refresh();

        return $post;
    }
}
