<?php

use Flarum\Api\Serializer\ForumSerializer;
use Flarum\Api\Serializer\PostSerializer;
use Flarum\Extend;
use Flarum\Post\Post;
use Itqan\Reactions\Access\PostPolicy;
use Itqan\Reactions\Api\ReactController;
use Itqan\Reactions\Api\ReactionSummaryLoader;
use Itqan\Reactions\Reaction;
use Itqan\Reactions\ReactionType;

return [
    (new Extend\Frontend('forum'))
        ->js(__DIR__.'/js/dist/forum.js')
        ->css(__DIR__.'/less/forum.less'),

    new Extend\Locales(__DIR__.'/locale'),

    (new Extend\Routes('api'))
        ->post('/posts/{id}/reactions', 'itqan-reactions.react', ReactController::class),

    (new Extend\Model(Post::class))
        ->hasMany('itqanReactions', Reaction::class, 'post_id'),

    (new Extend\Policy())
        ->modelPolicy(Post::class, PostPolicy::class),

    (new Extend\ApiSerializer(PostSerializer::class))
        ->attributes(ReactionSummaryLoader::class),

    (new Extend\ApiSerializer(ForumSerializer::class))
        ->attribute('itqanReactionTypes', function (ForumSerializer $serializer) {
            return ReactionType::enabled()
                ->orderBy('position')
                ->get()
                ->map(function (ReactionType $type) {
                    return [
                        'id' => (int) $type->id,
                        'identifier' => $type->identifier,
                        'emoji' => $type->emoji,
                        'label' => $type->label,
                    ];
                })
                ->values()
                ->all();
        }),

    (new Extend\ApiController(\Flarum\Api\Controller\ShowDiscussionController::class))
        ->load(['posts.itqanReactions']),

    (new Extend\ApiController(\Flarum\Api\Controller\ListPostsController::class))
        ->load(['itqanReactions']),
];
