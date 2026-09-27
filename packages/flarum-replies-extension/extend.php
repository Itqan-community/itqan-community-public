<?php

use Flarum\Api\Controller\ListDiscussionsController;
use Flarum\Api\Serializer\BasicPostSerializer;
use Flarum\Api\Serializer\PostSerializer;
use Flarum\Extend;
use Flarum\Post\Event\Saving;
use Flarum\Post\Post;
use Mtareq\NestedReplies\Access\PostPolicy;
use Mtareq\NestedReplies\Api\VotePostController;
use Mtareq\NestedReplies\Listener\StoreReplyParent;
use Mtareq\NestedReplies\PostReply;
use Mtareq\NestedReplies\PostVote;
use Mtareq\NestedReplies\Provider\SortMapProvider;
use Mtareq\NestedReplies\Vote\VoteCounts;

$extenders = [
    (new Extend\Frontend('forum'))
        ->js(__DIR__.'/js/dist/forum.js')
        ->css(__DIR__.'/less/forum.less'),

    (new Extend\Frontend('admin'))
        ->js(__DIR__.'/js/dist/admin.js'),

    (new Extend\Settings())
        ->default('mtareq-nested-replies.enabled', '1')
        ->default('mtareq-nested-replies.max_depth', '5')
        ->default('mtareq-nested-replies.show_votes', '1')
        ->default('mtareq-nested-replies.show_reply_tag', '1')
        ->default('mtareq-nested-replies.show_replied_indicator', '1')
        ->default('mtareq-nested-replies.like_color', '#ff4500')
        ->default('mtareq-nested-replies.start_at_first_post', '1')
        ->default('mtareq-nested-replies.visible_replies', '1')
        ->default('mtareq-nested-replies.show_scrubber', '1')
        ->default('mtareq-nested-replies.reply_form', 'quick')
        ->default('mtareq-nested-replies.highlight_color', '#00c853')
        ->default('mtareq-nested-replies.legacy_mentions', '0')
        ->serializeToForum('nestedRepliesEnabled', 'mtareq-nested-replies.enabled', 'boolval')
        ->serializeToForum('nestedRepliesMaxDepth', 'mtareq-nested-replies.max_depth', 'intval')
        ->serializeToForum('nestedRepliesShowVotes', 'mtareq-nested-replies.show_votes', 'boolval')
        ->serializeToForum('nestedRepliesShowReplyTag', 'mtareq-nested-replies.show_reply_tag', 'boolval')
        ->serializeToForum('nestedRepliesShowRepliedIndicator', 'mtareq-nested-replies.show_replied_indicator', 'boolval')
        ->serializeToForum('nestedRepliesLikeColor', 'mtareq-nested-replies.like_color')
        ->serializeToForum('nestedRepliesStartAtFirstPost', 'mtareq-nested-replies.start_at_first_post', 'boolval')
        ->serializeToForum('nestedRepliesVisibleReplies', 'mtareq-nested-replies.visible_replies', 'intval')
        ->serializeToForum('nestedRepliesShowScrubber', 'mtareq-nested-replies.show_scrubber', 'boolval')
        ->serializeToForum('nestedRepliesReplyForm', 'mtareq-nested-replies.reply_form')
        ->serializeToForum('nestedRepliesHighlightColor', 'mtareq-nested-replies.highlight_color')
        ->serializeToForum('nestedRepliesLegacyMentions', 'mtareq-nested-replies.legacy_mentions', 'boolval'),

    // --- Vote authorization --------------------------------------------------
    (new Extend\Policy())
        ->modelPolicy(Post::class, PostPolicy::class),

    // --- Discussion-list sorts: votes (+ core's commentCount) ----------------
    // Prime the first-post scores for the whole page in one query so the list's
    // vote rails do not N+1. (prepareDataForSerialization's callback receives
    // ($controller, $data, $request, $document) on Flarum 1.x.)
    (new Extend\ApiController(ListDiscussionsController::class))
        ->addSortField('votes')
        ->prepareDataForSerialization(function ($controller, $data, $request) {
            $ids = [];
            foreach ($data as $discussion) {
                if ($discussion && $discussion->first_post_id) {
                    $ids[] = (int) $discussion->first_post_id;
                }
                if ($discussion && $discussion->most_relevant_post_id) {
                    $ids[] = (int) $discussion->most_relevant_post_id;
                }
            }
            if ($ids) {
                VoteCounts::primeIds($ids, \Flarum\Http\RequestUtil::getActor($request));
            }
        }),

    // The details page serializes a whole post stream; prime it once.
    (new Extend\ApiController(\Flarum\Api\Controller\ShowDiscussionController::class))
        ->prepareDataForSerialization(function ($controller, $discussion, $request) {
            if ($discussion && $discussion->id) {
                VoteCounts::primeOwnForDiscussion((int) $discussion->id, \Flarum\Http\RequestUtil::getActor($request));
            }
        }),

    // Posts fetched directly (the reply tree uses /api/posts) are serialized
    // outside the discussion include; prime the page in one go.
    (new Extend\ApiController(\Flarum\Api\Controller\ListPostsController::class))
        ->prepareDataForSerialization(function ($controller, $data, $request) {
            $ids = [];
            foreach ($data as $post) {
                if ($post) {
                    $ids[] = (int) $post->id;
                }
            }
            if ($ids) {
                VoteCounts::primeIds($ids, \Flarum\Http\RequestUtil::getActor($request));
            }
        }),

    // --- Server sort map (the preloaded first page) --------------------------
    (new Extend\ServiceProvider())
        ->register(SortMapProvider::class),

    (new Extend\Routes('api'))
        ->post('/mtareq-nested-replies/posts/{id}/vote', 'mtareq-nested-replies.vote', VotePostController::class),

    (new Extend\Event())
        ->listen(Saving::class, StoreReplyParent::class),

    new Extend\Locales(__DIR__.'/locale'),
];

if (class_exists(\Flarum\Api\Resource\PostResource::class)) {
    // Flarum 2.x
    $extenders[] = (new Extend\ApiResource(\Flarum\Api\Resource\PostResource::class))
        ->fields(function () {
            return [
                \Flarum\Api\Schema\Integer::make('votes')
                    ->get(function ($post) {
                        return (int) PostVote::query()->where('post_id', $post->id)->sum('value');
                    }),

                \Flarum\Api\Schema\Str::make('userVote')
                    ->nullable()
                    ->get(function ($post, $context) {
                        $actor = $context->getActor();

                        if (! $actor || ! $actor->exists) {
                            return null;
                        }

                        $vote = PostVote::query()
                            ->where('post_id', $post->id)
                            ->where('user_id', $actor->id)
                            ->first();

                        return $vote ? ($vote->value > 0 ? 'up' : 'down') : null;
                    }),

                \Flarum\Api\Schema\Integer::make('replyToPostId')
                    ->nullable()
                    ->writableOnCreate()
                    ->get(function ($post) {
                        $link = PostReply::query()->where('post_id', $post->id)->first();

                        return $link ? (int) $link->parent_post_id : null;
                    })
                    ->set(function ($post, $value, $context) {
                        // Persistence is owned by the Saving listener.
                    }),

                \Flarum\Api\Schema\Integer::make('nestedRepliesReplyCount')
                    ->get(function ($post) {
                        return PostReply::subtreeCount($post->discussion_id, $post->id);
                    }),
            ];
        });
} else {
    // Flarum 1.x
    // Post attributes that must be present wherever a post is serialized,
    // including includes (firstPost/lastPost/mostRelevantPost use
    // BasicPostSerializer). PostSerializer extends this, so the details page
    // is unaffected.
    $extenders[] = (new Extend\ApiSerializer(BasicPostSerializer::class))
        ->attributes(function ($serializer, $post) {
            $actor = $serializer->getActor();

            return [
                'votes' => VoteCounts::forPosts([(int) $post->id], $actor)[(int) $post->id] ?? 0,
                'userVote' => VoteCounts::userVotes([(int) $post->id], $actor)[(int) $post->id] ?? null,
                // Display hint only — registered actors who did not author this
                // post. Cheap (no query); the PostPolicy still gates the write, so
                // this never widens access, it only stops the rail from firing a
                // request the server would deny.
                'canVote' => (bool) ($actor->exists && (int) $post->user_id !== (int) $actor->id),
            ];
        });

    $extenders[] = (new Extend\ApiSerializer(PostSerializer::class))
        ->attribute('replyToPostId', function ($serializer, $post) {
            $link = PostReply::query()->where('post_id', $post->id)->first();

            return $link ? (int) $link->parent_post_id : null;
        })
        ->attribute('nestedRepliesReplyCount', function ($serializer, $post) {
            return PostReply::subtreeCount($post->discussion_id, $post->id);
        });
}

return $extenders;
