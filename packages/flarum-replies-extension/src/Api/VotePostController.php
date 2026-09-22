<?php

namespace Mtareq\NestedReplies\Api;

use Flarum\Api\Controller\AbstractShowController;
use Flarum\Api\Serializer\PostSerializer;
use Flarum\Http\RequestUtil;
use Flarum\Post\Post;
use Illuminate\Support\Arr;
use Mtareq\NestedReplies\PostVote;
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
        $actor->assertRegistered();

        $id = Arr::get($request->getQueryParams(), 'id');
        $post = Post::findOrFail($id);

        $body = $request->getParsedBody();
        $direction = is_array($body) ? Arr::get($body, 'direction') : null;

        $value = $direction === 'up' ? 1 : ($direction === 'down' ? -1 : null);

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

        return $post;
    }
}
