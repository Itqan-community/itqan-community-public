<?php

use Flarum\Api\Controller\ListDiscussionsController;
use Flarum\Api\Serializer\DiscussionSerializer;
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

    // --- Vote authorization (canVote + controller assert) -------------------
    (new Extend\Policy())
        ->modelPolicy(Post::class, PostPolicy::class),

    // --- Discussion list sorts: votes / hotness ------------------------------
    (new Extend\ApiController(ListDiscussionsController::class))
        ->addSortField('votes')
        ->addSortField('hotness'),

    // --- Frontend+server sort map (dropdown labels come from sortMap keys) ---
    (new Extend\ServiceProvider())
        ->register(SortMapProvider::class),

    // --- Batch-prime the list page's firstPost scores (actor-independent) ---
    // Core invokes serialization-prep callbacks as ($controller, $data, $request,
    // $document) — $data is the SECOND argument (AbstractSerializeController).
    (new Extend\ApiController(ListDiscussionsController::class))
        ->prepareDataForSerialization(function ($controller, $data) {
            $ids = [];
            foreach ($data as $discussion) {
                if ($discussion && $discussion->first_post_id) {
                    $ids[] = (int) $discussion->first_post_id;
                }
            }
            if ($ids) {
                VoteCounts::prime($ids);
            }
        }),

    // --- Batch-prime own votes per discussion (stream serialization) --------
    // The PostSerializer attributes closure below calls primeOwnForDiscussion()
    // itself — one line per post, one query per discussion thanks to its memo.
    // No controller hook needed here; listed for discoverability.

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
                        // Batched request-scoped loader — same contract as the
                        // v1 serializer branch (spec §2).
                        return VoteCounts::forPosts([(int) $post->id], null)[(int) $post->id] ?? 0;
                    }),

                \Flarum\Api\Schema\Str::make('userVote')
                    ->nullable()
                    ->get(function ($post, $context) {
                        $actor = $context->getActor();

                        if (! $actor || ! $actor->exists) {
                            return null;
                        }

                        VoteCounts::primeOwnForDiscussion((int) $post->discussion_id, $actor);

                        return VoteCounts::userVotes([(int) $post->id], $actor)[(int) $post->id] ?? null;
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
    $extenders[] = (new Extend\ApiSerializer(PostSerializer::class))
        ->attributes(function ($serializer, $post) {
            $actor = $serializer->getActor();

            // One own-vote query per discussion per request (memoized), then
            // everything below is served from the loader's memo.
            VoteCounts::primeOwnForDiscussion((int) $post->discussion_id, $actor);

            $sum = VoteCounts::forPosts([(int) $post->id], $actor)[(int) $post->id] ?? 0;

            return [
                'votes' => $sum,
                'userVote' => VoteCounts::userVotes([(int) $post->id], $actor)[(int) $post->id] ?? null,
                'canVote' => (bool) $actor->can('vote', $post),
            ];
        })
        ->attribute('replyToPostId', function ($serializer, $post) {
            $link = PostReply::query()->where('post_id', $post->id)->first();

            return $link ? (int) $link->parent_post_id : null;
        })
        ->attribute('nestedRepliesReplyCount', function ($serializer, $post) {
            return PostReply::subtreeCount($post->discussion_id, $post->id);
        });

    $extenders[] = (new Extend\ApiSerializer(DiscussionSerializer::class))
        ->attributes(function ($serializer, $discussion) {
            $actor = $serializer->getActor();
            $firstPostId = (int) $discussion->first_post_id;

            return [
                'votes' => $firstPostId
                    ? (VoteCounts::forPosts([$firstPostId], $actor)[$firstPostId] ?? 0)
                    : 0,
                'firstPostId' => $firstPostId,
                'userVote' => $firstPostId
                    ? (VoteCounts::userVotes([$firstPostId], $actor)[$firstPostId] ?? null)
                    : null,
                // canVote on a list row is a display hint only, decided from
                // the session (guests can't; your own first post can't — the
                // client knows the author). The full PostPolicy still gates the
                // actual POST. No Post::find() here: 20 rows = 20 queries.
                'canVote' => (bool) $actor->exists,
            ];
        });
}

/*
 * Flarum 2.0 upgrade note (spec §10 risk register):
 * - addSortField / AddSortField exists in v1; v2's sort extension point must be
 *   re-verified (candidate: Extend\Sort on the Discussion resource).
 * - ApiSerializer extenders become Resource fields; PostSerializer/DiscussionSerializer
 *   attributes closures map to PostResource/DiscussionResource::fields(...).
 * - SortMapProvider's container binding name (flarum.forum.discussions.sortmap)
 *   may change; verify at upgrade.
 * - canVote is v1-serializer-only today: add it as a Boolean field on the v2
 *   PostResource/DiscussionResource at upgrade (the v2 branch above ships
 *   votes/userVote through VoteCounts, but not canVote).
 * - Verify prepareDataForSerialization's callback arity on v2 (v1 calls
 *   ($controller, $data, $request, $document)).
 */

return $extenders;
