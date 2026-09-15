<?php

use Flarum\Api\Controller\CreatePostController;
use Flarum\Api\Controller\ListDiscussionsController;
use Flarum\Api\Controller\ShowDiscussionController;
use Flarum\Api\Serializer\BasicPostSerializer;
use Flarum\Api\Serializer\DiscussionSerializer;
use Flarum\Api\Serializer\PostSerializer;
use Flarum\Discussion\Discussion;
use Flarum\Extend;
use Flarum\Post\Event\Deleted;
use Flarum\Post\Event\Saving;
use Flarum\Post\Post;
use Flarum\User\User;
use Itqan\Discussions\Access\PostPolicy;
use Itqan\Discussions\Api\ListCommentTreeController;
use Itqan\Discussions\Api\ListPostRepliesController;
use Itqan\Discussions\Api\LoadTreePostsRelationship;
use Itqan\Discussions\Api\SanitizeCreatePostPosts;
use Itqan\Discussions\Api\VoteController;
use Itqan\Discussions\Console\BackfillParentIdsCommand;
use Itqan\Discussions\Console\BackfillRootDepthCommand;
use Itqan\Discussions\Listener\SaveParentIdToPost;
use Itqan\Discussions\Listener\UpdateReplyCountOnDelete;
use Itqan\Discussions\Provider\SortMapProvider;
use Itqan\Discussions\Vote\Vote;

return [
    (new Extend\Frontend('forum'))
        ->js(__DIR__.'/js/dist/forum.js')
        ->css(__DIR__.'/less/forum.less'),

    new Extend\Locales(__DIR__.'/locale'),

    (new Extend\Routes('api'))
        ->patch('/posts/{id}/vote', 'itqan-discussions.vote', VoteController::class)
        ->get('/discussions/{id}/comment-tree', 'itqan-discussions.comment-tree', ListCommentTreeController::class)
        ->get('/posts/{id}/replies', 'itqan-discussions.post-replies', ListPostRepliesController::class),

    (new Extend\Settings())
        ->default('itqan-discussions.maxDepth', '4')
        ->default('itqan-discussions.maxNodesPerRoot', '20')
        ->default('itqan-discussions.rootPageSize', '20'),

    // Relationships
    (new Extend\Model(Post::class))
        ->hasMany('postVotes', Vote::class, 'post_id')
        ->belongsTo('parent', Post::class, 'parent_id')
        ->hasMany('replies', Post::class, 'parent_id'),

    (new Extend\Policy())
        ->modelPolicy(Post::class, PostPolicy::class),

    // Preserve thread tree for deleted comments that have nested replies (Option A)
    (new Extend\ModelVisibility(Post::class))
        ->scope(function (User $actor, $query) {
            if (! $actor->hasPermission('discussion.hidePosts')) {
                $queryBuilder = $query->getQuery();
                if (isset($queryBuilder->wheres)) {
                    foreach ($queryBuilder->wheres as &$where) {
                        if (isset($where['type']) && $where['type'] === 'Nested' && isset($where['query']->wheres)) {
                            foreach ($where['query']->wheres as $subWhere) {
                                if (isset($subWhere['column']) && $subWhere['column'] === 'posts.hidden_at') {
                                    $where['query']->orWhere(function ($subQ) {
                                        $subQ->whereNotNull('posts.hidden_at')
                                             ->where('posts.reply_count', '>', 0);
                                    });
                                    break 2;
                                }
                            }
                        }
                    }
                }
            }
        }),

    // BasicPostSerializer attributes for threaded / nested replies
    (new Extend\ApiSerializer(BasicPostSerializer::class))
        ->attribute('contentHtml', function (BasicPostSerializer $serializer, Post $post, array $attributes) {
            if ($post->hidden_at && ! $serializer->getActor()->can('edit', $post) && ! $serializer->getActor()->hasPermission('discussion.hidePosts')) {
                return '<p class="itqan-deleted-post-notice"><em>[تم حذف هذا التعليق]</em></p>';
            }
            return $attributes['contentHtml'] ?? null;
        })
        ->attribute('parentId', function (BasicPostSerializer $serializer, Post $post) {
            return $post->parent_id ? (int) $post->parent_id : null;
        })
        ->attribute('replyCount', function (BasicPostSerializer $serializer, Post $post) {
            return (int) ($post->reply_count ?? 0);
        })
        ->attribute('rootId', function (BasicPostSerializer $serializer, Post $post) {
            return $post->root_id ? (int) $post->root_id : null;
        })
        ->attribute('depth', function (BasicPostSerializer $serializer, Post $post) {
            return (int) ($post->depth ?? 0);
        })
        ->attribute('hasMoreReplies', function (BasicPostSerializer $serializer, Post $post) {
            if (isset($post->has_more_replies)) {
                return (bool) $post->has_more_replies;
            }
            return false;
        }),

    (new Extend\ApiSerializer(PostSerializer::class))
        ->attribute('votes', function (PostSerializer $serializer, Post $post) {
            return (int) $post->votes;
        })
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

    // The discussion's own score, taken from its opening post.
    (new Extend\ApiSerializer(DiscussionSerializer::class))
        ->attribute('votes', function (DiscussionSerializer $serializer, Discussion $discussion) {
            return (int) $discussion->votes;
        })
        ->attribute('firstPostId', function (DiscussionSerializer $serializer, Discussion $discussion) {
            return $discussion->first_post_id;
        })
        ->attribute('userVote', function (DiscussionSerializer $serializer, Discussion $discussion) {
            $actor = $serializer->getActor();
            $post = $discussion->firstPost;

            if (! $actor->exists || ! $post) {
                return null;
            }

            $vote = $post->postVotes->firstWhere('user_id', $actor->id);

            return $vote ? (int) $vote->value : 0;
        })
        ->attribute('canVote', function (DiscussionSerializer $serializer, Discussion $discussion) {
            $post = $discussion->firstPost;

            return $post ? $serializer->getActor()->can('vote', $post) : false;
        })
        ->attribute('rootCommentCount', function (DiscussionSerializer $serializer, Discussion $discussion) {
            return isset($discussion->root_comment_count) ? (int) $discussion->root_comment_count : null;
        })
        ->attribute('rootsLoaded', function (DiscussionSerializer $serializer, Discussion $discussion) {
            return isset($discussion->roots_loaded) ? (int) $discussion->roots_loaded : null;
        })
        ->attribute('rootsHasMore', function (DiscussionSerializer $serializer, Discussion $discussion) {
            return isset($discussion->roots_has_more) ? (bool) $discussion->roots_has_more : null;
        })
        ->attribute('rootsHasPrevious', function (DiscussionSerializer $serializer, Discussion $discussion) {
            return isset($discussion->roots_has_previous) ? (bool) $discussion->roots_has_previous : null;
        })
        ->attribute('rootsOffset', function (DiscussionSerializer $serializer, Discussion $discussion) {
            return isset($discussion->roots_offset) ? (int) $discussion->roots_offset : null;
        })
        ->attribute('commentSort', function (DiscussionSerializer $serializer, Discussion $discussion) {
            return $discussion->comment_sort ?? null;
        }),

    // Event listeners for parent_id persistence and reply_count synchronization
    (new Extend\Event())
        ->listen(Saving::class, SaveParentIdToPost::class)
        ->listen(Deleted::class, UpdateReplyCountOnDelete::class),

    // The two orderings the discussion list offers.
    (new Extend\ApiController(ListDiscussionsController::class))
        ->addSortField('votes')
        ->addSortField('hotness')
        ->load(['firstPost', 'firstPost.postVotes']),

    // SortMap provider
    (new Extend\ServiceProvider())
        ->register(SortMapProvider::class),

    // Load post votes and tree posts for discussions
    (new Extend\ApiController(ShowDiscussionController::class))
        ->load(['posts.postVotes'])
        ->prepareDataForSerialization(LoadTreePostsRelationship::class),

    // Prevent CreatePost from dumping every post ID into the client payload.
    (new Extend\ApiController(CreatePostController::class))
        ->prepareDataForSerialization(SanitizeCreatePostPosts::class),

    (new Extend\Console())
        ->command(BackfillParentIdsCommand::class)
        ->command(BackfillRootDepthCommand::class),
];
