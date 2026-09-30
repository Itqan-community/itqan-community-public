<?php

namespace Itqan\Reactions\Api;

use Flarum\Api\Serializer\PostSerializer;
use Flarum\Post\Post;
use Itqan\Reactions\ReactionRepository;

class ReactionSummaryLoader
{
    /** @var ReactionRepository */
    protected $reactions;

    public function __construct(ReactionRepository $reactions)
    {
        $this->reactions = $reactions;
    }

    public function __invoke(PostSerializer $serializer, Post $post, array $attributes): array
    {
        $actor = $serializer->getActor();
        $attributes['reactionSummary'] = $this->reactions->summaryFor($post, $actor);
        $attributes['canReact'] = $actor->can('react', $post);

        return $attributes;
    }
}
