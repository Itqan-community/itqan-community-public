<?php

use Flarum\Api\Serializer\DiscussionSerializer;
use Flarum\Api\Serializer\PostSerializer;
use Flarum\Extend;
use Flarum\Discussion\Discussion;
use Flarum\Post\Post;
use Itqan\Discussions\Access\PostPolicy;
use Itqan\Discussions\Api\VoteController;
use Itqan\Discussions\Provider\SortMapProvider;
use Itqan\Discussions\Vote\Vote;
use Flarum\Api\Controller\ListDiscussionsController;

return [
    (new Extend\Frontend('forum'))
        ->js(__DIR__.'/js/dist/forum.js')
        ->css(__DIR__.'/less/forum.less'),

    new Extend\Locales(__DIR__.'/locale'),

    (new Extend\Routes('api'))
        ->patch('/posts/{id}/vote', 'itqan-discussions.vote', VoteController::class),

    // Named `postVotes`, not `votes`: `posts.votes` is the stored score, and a
    // relation of the same name would shadow the column — `$post->votes`
    // would hand back a relation where the rest of the code expects an int.
    (new Extend\Model(Post::class))
        ->hasMany('postVotes', Vote::class, 'post_id'),

    (new Extend\Policy())
        ->modelPolicy(Post::class, PostPolicy::class),

    (new Extend\ApiSerializer(PostSerializer::class))
        // `votes` is the stored score, not a count of the rows: the whole
        // reason the column exists is that nothing should aggregate the table
        // to render a post.
        ->attribute('votes', function (PostSerializer $serializer, Post $post) {
            return (int) $post->votes;
        })
        // What this reader did, so the buttons can show their state without a
        // second request. Null for guests, who cannot vote anyway.
        ->attribute('userVote', function (PostSerializer $serializer, Post $post) {
            $actor = $serializer->getActor();

            if (! $actor->exists) {
                return null;
            }

            $vote = $post->postVotes->firstWhere('user_id', $actor->id);

            return $vote ? (int) $vote->value : 0;
        })
        ->attribute('canVote', function (PostSerializer $serializer, Post $post) {
            return $serializer->getActor()->can('vote', $post);
        }),

    // The discussion's own score, taken from its opening post. The list needs
    // it to show a score beside each row, and the sort needs it in the
    // payload to make the ordering legible rather than mysterious.
    (new Extend\ApiSerializer(DiscussionSerializer::class))
        ->attribute('votes', function (DiscussionSerializer $serializer, Discussion $discussion) {
            return (int) $discussion->votes;
        }),

    // The two orderings the discussion list offers. Both read an indexed
    // column; neither aggregates post_votes at query time.
    (new Extend\ApiController(ListDiscussionsController::class))
        ->addSortField('votes')
        ->addSortField('hotness'),

    // The frontend map alone is not enough; see SortMapProvider.
    (new Extend\ServiceProvider())
        ->register(SortMapProvider::class),

    // Without this the serializer's `userVote` lookup would fire a query for
    // every post in the stream.
    (new Extend\ApiController(Flarum\Api\Controller\ShowDiscussionController::class))
        ->load(['posts.postVotes']),
];
