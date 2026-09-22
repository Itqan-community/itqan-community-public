<?php

use Flarum\Api\Serializer\PostSerializer;
use Flarum\Extend;
use Flarum\Post\Event\Saving;
use Mtareq\NestedReplies\Api\VotePostController;
use Mtareq\NestedReplies\Listener\StoreReplyParent;
use Mtareq\NestedReplies\PostReply;
use Mtareq\NestedReplies\PostVote;

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
    $extenders[] = (new Extend\ApiSerializer(PostSerializer::class))
        ->attribute('votes', function ($serializer, $post) {
            return (int) (PostVote::query()->where('post_id', $post->id)->sum('value') ?? 0);
        })
        ->attribute('userVote', function ($serializer, $post) {
            $actor = $serializer->getActor();

            if (! $actor || ! $actor->exists) {
                return null;
            }

            $vote = PostVote::query()
                ->where('post_id', $post->id)
                ->where('user_id', $actor->id)
                ->first();

            if (! $vote) {
                return null;
            }

            return $vote->value > 0 ? 'up' : 'down';
        })
        ->attribute('replyToPostId', function ($serializer, $post) {
            $link = PostReply::query()->where('post_id', $post->id)->first();

            return $link ? (int) $link->parent_post_id : null;
        })
        ->attribute('nestedRepliesReplyCount', function ($serializer, $post) {
            return PostReply::subtreeCount($post->discussion_id, $post->id);
        });
}

return $extenders;
