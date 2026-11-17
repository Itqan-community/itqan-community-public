<?php

declare(strict_types=1);

namespace Itqan\CommunityMcp\Mcp\Tools;

use Flarum\User\User;
use InvalidArgumentException;
use Itqan\CommunityMcp\Formatter\PostMarkdownFormatter;
use Itqan\CommunityMcp\Search\ForumSearchService;
use Itqan\CommunityMcp\Support\Entity;
use RuntimeException;

/**
 * `get_discussion` — one discussion with its posts in reading order.
 *
 * Branched/nested replies are rendered as a tree hint on every reply
 * ("in reply to #N by @user") because Flarum's flat post list alone loses the
 * conversation shape. Soft-deleted parents that the community extension keeps
 * visible as placeholders are masked: content becomes "[post hidden]" and the
 * author becomes "[محذوف]", so the tree stays connected without leaking the
 * removed post.
 *
 * `renderDiscussionMarkdown()` is public so the LLM Markdown controller added in
 * Batch 3 (`/api/mcp/discussions/{id}`) renders exactly the same text.
 */
class GetDiscussionTool implements ToolInterface
{
    private const DEFAULT_POST_LIMIT = 25;

    private ForumSearchService $search;

    public function __construct(ForumSearchService $search)
    {
        $this->search = $search;
    }

    public function getName(): string
    {
        return 'get_discussion';
    }

    public function getDescription(): string
    {
        return 'Read one Itqan Community (مجتمع إتقان) discussion as Markdown: title, '
            .'author, tags, statistics, then the opening post and the comments in '
            .'chronological order. Nested replies include the post they reply to, their '
            .'depth and their sub-reply count; votes and likes are shown when present. '
            .'Use `post_offset` to page through long threads.';
    }

    public function getInputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'discussion_id' => [
                    'type' => 'integer',
                    'minimum' => 1,
                    'description' => 'Numeric discussion ID (the `id` returned by `search_discussions`).',
                ],
                'post_offset' => [
                    'type' => 'integer',
                    'minimum' => 0,
                    'description' => 'Number of posts to skip before the first returned post (default 0).',
                    'default' => 0,
                ],
                'post_limit' => [
                    'type' => 'integer',
                    'minimum' => 1,
                    'maximum' => 50,
                    'description' => 'Maximum posts to return (default 25, maximum 50).',
                    'default' => 25,
                ],
            ],
            'required' => ['discussion_id'],
            'additionalProperties' => false,
        ];
    }

    public function getAnnotations(): array
    {
        return [
            'readOnlyHint' => true,
            'openWorldHint' => false,
        ];
    }

    public function execute(array $arguments, User $actor): string
    {
        $discussionId = isset($arguments['discussion_id']) && is_numeric($arguments['discussion_id'])
            ? (int) $arguments['discussion_id']
            : 0;

        if ($discussionId <= 0) {
            throw new InvalidArgumentException('`discussion_id` is required and must be a positive integer.');
        }

        $postOffset = isset($arguments['post_offset']) && is_numeric($arguments['post_offset'])
            ? max(0, (int) $arguments['post_offset'])
            : 0;

        $postLimit = isset($arguments['post_limit']) && is_numeric($arguments['post_limit'])
            ? (int) $arguments['post_limit']
            : self::DEFAULT_POST_LIMIT;

        $markdown = $this->renderDiscussionMarkdown($discussionId, $actor, $postOffset, $postLimit);

        if ($markdown === null) {
            throw new RuntimeException(
                sprintf('Discussion %d was not found or is not visible to the current user.', $discussionId)
            );
        }

        return $markdown;
    }

    /**
     * Render a discussion page, or null when it does not exist / is not visible.
     */
    public function renderDiscussionMarkdown(
        int $discussionId,
        User $actor,
        int $postOffset = 0,
        int $postLimit = self::DEFAULT_POST_LIMIT
    ): ?string {
        $postOffset = max(0, $postOffset);
        $postLimit = max(1, min(ForumSearchService::MAX_POST_PAGE, $postLimit));

        $discussion = $this->search->findVisibleDiscussion($discussionId, $actor);

        if ($discussion === null) {
            return null;
        }

        $page = $this->search->getDiscussionPosts($discussion, $actor, $postOffset, $postLimit);
        $posts = $page['posts'];
        $total = $page['total'];

        $metadata = $this->search->resolvePostThreadMetadata($posts, $actor);
        $engagement = $this->search->getDiscussionEngagement($discussion, $actor);
        $baseUrl = $this->search->getBaseUrl();

        $replies = max(0, $total - 1);
        $hasBranches = false;

        foreach ($metadata as $meta) {
            if (($meta['reply_to_post_id'] ?? null) !== null) {
                $hasBranches = true;

                break;
            }
        }

        $lines = [];
        $lines[] = '# '.(string) Entity::attr($discussion, 'title', '');
        $lines[] = '';
        $lines[] = '- **الرابط (URL):** '.$baseUrl.'/d/'.$discussionId;
        $lines[] = '- **معرف المناقشة (Discussion ID):** '.$discussionId;
        $lines[] = '- **الكاتب (Author):** '.$this->authorLabel(Entity::attr($discussion, 'user'));

        $createdAt = Entity::date(Entity::attr($discussion, 'created_at'));

        if ($createdAt !== null) {
            $lines[] = '- **تاريخ الإنشاء (Created):** '.$createdAt;
        }

        $lines[] = '- **الوسوم (Tags):** '.$this->tagLine($discussion, $actor);

        if (Entity::bool($discussion, 'is_sticky')) {
            $lines[] = '- **مثبّتة (Sticky):** نعم 📌';
        }

        if ($engagement['detected_lang'] !== null) {
            $lines[] = '- **اللغة (Language):** `'.$engagement['detected_lang'].'`';
        }

        $stats = [
            'المشاركات (Posts): '.$total,
            'الردود (Replies): '.$replies,
            'المشاركون (Participants): '.Entity::int($discussion, 'participant_count'),
        ];

        if ($engagement['votes'] !== 0) {
            $stats[] = '👍 الأصوات (Votes): '.$engagement['votes'];
        }

        if ($engagement['likes_count'] > 0) {
            $stats[] = '❤️ الإعجابات (Likes): '.$engagement['likes_count'];
        }

        $lines[] = '- **الإحصاءات (Stats):** '.implode(' · ', $stats);

        if ($hasBranches) {
            $lines[] = '';
            $lines[] = '> هذا الموضوع يستخدم الردود المتشعبة (nested replies). كل مشاركة تبيّن المشاركة التي تردّ عليها، وعمقها، وعدد ردودها الفرعية.';
            $lines[] = '> This thread uses branched replies; each post lists the post it answers, its depth and its sub-replies.';
        }

        $lines[] = '';
        $lines[] = '---';

        if ($posts === []) {
            $lines[] = '';
            $lines[] = '_لا توجد مشاركات ظاهرة في هذه الصفحة (No visible posts on this page)._';

            return trim(implode("\n", $lines));
        }

        foreach ($posts as $post) {
            $postId = (int) Entity::attr($post, 'id');
            $number = Entity::int($post, 'number');
            $meta = $metadata[$postId] ?? [];
            $hidden = Entity::isHidden($post);

            $lines[] = '';
            $lines[] = $this->postHeader($post, $number, $hidden);
            $lines[] = '';
            $lines[] = '- **الرابط المباشر (Permalink):** '.$baseUrl.'/d/'.$discussionId.'/'.$number;

            $parentId = $meta['reply_to_post_id'] ?? null;

            if ($parentId !== null) {
                $parentNumber = $meta['parent_post_number'] ?? null;
                $parentUsername = $meta['parent_author_username'] ?? null;
                $parentLabel = $parentUsername !== null
                    ? '@'.$parentUsername
                    : (string) ($meta['parent_author_display_name'] ?? Entity::DELETED_LABEL);

                $lines[] = sprintf(
                    '- **رداً على (In reply to):** المشاركة #%s بواسطة %s (Post ID: `%d`)',
                    $parentNumber !== null ? (string) $parentNumber : '?',
                    $parentLabel,
                    (int) $parentId
                );

                $depth = (int) ($meta['depth'] ?? 0);

                if ($depth > 0) {
                    $lines[] = '- **العمق (Depth):** '.$depth;
                }

                $subReplies = (int) ($meta['reply_count'] ?? 0);

                if ($subReplies > 0) {
                    $lines[] = '- **الردود الفرعية (Sub-replies):** '.$subReplies;
                }
            }

            if (! $hidden) {
                $votes = (int) ($meta['votes'] ?? 0);
                $likes = (int) ($meta['likes_count'] ?? 0);

                if ($votes !== 0 || $likes > 0) {
                    $engagementParts = [];

                    if ($votes !== 0) {
                        $engagementParts[] = '👍 الأصوات (Votes): '.$votes;
                    }

                    if ($likes > 0) {
                        $engagementParts[] = '❤️ الإعجابات (Likes): '.$likes;
                    }

                    $lines[] = '- '.implode(' · ', $engagementParts);
                }
            }

            $lines[] = '';
            $lines[] = PostMarkdownFormatter::toMarkdown($post, 15000);
        }

        if ($total > $postOffset + count($posts)) {
            $nextOffset = $postOffset + count($posts);
            $lines[] = '';
            $lines[] = '---';
            $lines[] = '';
            $lines[] = sprintf(
                '**الصفحة التالية (Next page):** تم عرض %d من %d مشاركة. استدعِ `get_discussion` مع `post_offset=%d` و`post_limit=%d` للمتابعة.',
                count($posts),
                $total,
                $nextOffset,
                $postLimit
            );
        }

        return trim(implode("\n", $lines));
    }

    private function postHeader($post, int $number, bool $hidden): string
    {
        if ($hidden) {
            $author = Entity::DELETED_LABEL;
        } else {
            $user = Entity::attr($post, 'user');
            $author = Entity::displayName($user);
            $username = Entity::username($user);

            if ($username !== null) {
                $author .= ' (@'.$username.')';
            }
        }

        $date = Entity::date(Entity::attr($post, 'created_at'));
        $header = sprintf('### مشاركة #%d — %s', $number, $author);

        if ($date !== null) {
            $header .= ' | '.$date;
        }

        return $header;
    }

    private function authorLabel($user): string
    {
        return Entity::authorLabel($user);
    }

    private function tagLine($discussion, User $actor): string
    {
        try {
            $tags = $discussion->tags;
        } catch (\Throwable) {
            return '—';
        }

        if (! is_iterable($tags)) {
            return '—';
        }

        // Never expose the name/slug of a restricted tag the actor cannot
        // view, even when the discussion itself is visible through another tag.
        $visible = [];

        foreach ($tags as $tag) {
            if (Entity::bool($tag, 'is_hidden')) {
                continue;
            }

            if (! Entity::bool($tag, 'is_restricted')) {
                $visible[] = $tag;

                continue;
            }

            try {
                if (method_exists($actor, 'can') && $actor->can('view', $tag)) {
                    $visible[] = $tag;
                }
            } catch (\Throwable) {
                // Deny by default.
            }
        }

        return Entity::tagList($visible);
    }
}
