<?php

declare(strict_types=1);

namespace Itqan\CommunityMcp\Mcp\Tools;

use Flarum\User\User;
use InvalidArgumentException;
use Itqan\CommunityMcp\Search\ForumSearchService;
use Itqan\CommunityMcp\Support\Entity;

/**
 * `search_posts` — search individual comments, including nested replies.
 */
class SearchPostsTool implements ToolInterface
{
    private ForumSearchService $search;

    public function __construct(ForumSearchService $search)
    {
        $this->search = $search;
    }

    public function getName(): string
    {
        return 'search_posts';
    }

    public function getDescription(): string
    {
        return 'Search individual posts (including branched/nested replies) in Itqan '
            .'Community by Arabic or English keywords. Each hit includes its discussion, '
            .'a direct permalink, the author, the post it replies to, votes/likes and a '
            .'short snippet around the matched keywords. Use it when `search_discussions` '
            .'finds the thread but you need the exact comment.';
    }

    public function getInputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'query' => [
                    'type' => 'string',
                    'description' => 'Short keywords to match inside post bodies, e.g. "الوقف والابتداء" or "whisper".',
                ],
                'discussion_id' => [
                    'type' => 'integer',
                    'minimum' => 1,
                    'description' => 'Restrict the search to one discussion ID.',
                ],
                'author' => [
                    'type' => 'string',
                    'description' => 'Filter by post author username (with or without a leading @).',
                ],
                'limit' => [
                    'type' => 'integer',
                    'minimum' => 1,
                    'maximum' => 25,
                    'description' => 'Maximum number of posts to return (default 10, maximum 25).',
                    'default' => 10,
                ],
            ],
            'required' => ['query'],
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
        $query = isset($arguments['query']) && is_scalar($arguments['query'])
            ? trim((string) $arguments['query'])
            : '';

        if ($query === '') {
            throw new InvalidArgumentException('`query` is required and must be a non-empty string.');
        }

        $discussionId = isset($arguments['discussion_id']) && is_numeric($arguments['discussion_id'])
            ? (int) $arguments['discussion_id']
            : null;

        $author = isset($arguments['author']) && is_scalar($arguments['author'])
            ? trim((string) $arguments['author'])
            : null;

        $limit = isset($arguments['limit']) && is_numeric($arguments['limit'])
            ? (int) $arguments['limit']
            : 10;

        $result = $this->search->searchPosts($actor, $query, $discussionId, $author, $limit);

        return $this->render($result);
    }

    /**
     * @param array<string, mixed> $result
     */
    public function render(array $result): string
    {
        $results = $result['results'] ?? [];
        $query = (string) ($result['query'] ?? '');
        $discussionId = $result['discussion_id'] ?? null;
        $author = $result['author'] ?? null;

        $lines = [];
        $lines[] = '# البحث في المشاركات (Post Search)';
        $lines[] = '';

        $filters = [];
        $filters[] = '**الاستعلام (Query):** «'.$query.'»';
        $filters[] = '**المناقشة (Discussion):** '.($discussionId !== null ? '#'.$discussionId : '—');
        $filters[] = '**الكاتب (Author):** '.($author !== null ? '@'.$author : '—');
        $filters[] = '**النتائج (Results):** '.count($results);

        $lines[] = implode(' · ', $filters);

        if (! empty($result['expanded'])) {
            $lines[] = '';
            $lines[] = '> تم توسيع البحث تلقائيًا بمتغيرات إملائية عربية (تشكيل، همزات، تاء مربوطة).';
        }

        $lines[] = '';

        if ($results === []) {
            $lines[] = '_لم يتم العثور على مشاركات مطابقة (No matching posts)._';
            $lines[] = '';
            $lines[] = 'اقتراحات (Suggestions):';
            $lines[] = '- جرّب كلمات مفتاحية أقصر أو مرادفًا عربيًا/إنجليزيًا.';
            $lines[] = '- ابحث أولًا في العناوين عبر `search_discussions`، ثم أعد المحاولة هنا بدون `discussion_id`.';

            return implode("\n", $lines);
        }

        foreach ($results as $index => $post) {
            $lines[] = '---';
            $lines[] = '';
            $lines[] = sprintf(
                '## %d. %s — مشاركة #%d',
                $index + 1,
                $post['discussion_title'] !== '' ? $post['discussion_title'] : 'مناقشة #'.$post['discussion_id'],
                $post['post_number']
            );

            $lines[] = '';
            $lines[] = '- **الرابط المباشر (Permalink):** '.$post['url'];
            $lines[] = '- **المناقشة (Discussion):** '.$post['discussion_title'].' — '.$post['discussion_url'].' (ID: '.$post['discussion_id'].')';
            $lines[] = '- **الكاتب (Author):** '.$this->authorLabel($post['author'] ?? null);

            if (! empty($post['created_at'])) {
                $lines[] = '- **التاريخ (Date):** '.$post['created_at'];
            }

            if (! empty($post['reply_to_post_id'])) {
                $parentNumber = $post['parent_post_number'] ?? null;
                $parentUsername = $post['parent_author_username'] ?? null;
                $parentLabel = $parentUsername !== null
                    ? '@'.$parentUsername
                    : (string) ($post['parent_author_display_name'] ?? Entity::DELETED_LABEL);

                $lines[] = sprintf(
                    '- **رداً على (In reply to):** المشاركة #%s بواسطة %s (Post ID: `%d`)',
                    $parentNumber !== null ? (string) $parentNumber : '?',
                    $parentLabel,
                    (int) $post['reply_to_post_id']
                );
            }

            $engagement = [];

            if ((int) ($post['votes'] ?? 0) !== 0) {
                $engagement[] = '👍 الأصوات (Votes): '.(int) $post['votes'];
            }

            if ((int) ($post['likes_count'] ?? 0) > 0) {
                $engagement[] = '❤️ الإعجابات (Likes): '.(int) $post['likes_count'];
            }

            if ($engagement !== []) {
                $lines[] = '- '.implode(' · ', $engagement);
            }

            if (! empty($post['snippet'])) {
                $lines[] = '';
                $lines[] = '**المقتطف (Snippet):**';
                $lines[] = '';

                foreach (explode("\n", (string) $post['snippet']) as $snippetLine) {
                    $lines[] = '> '.$snippetLine;
                }
            }

            $lines[] = '';
        }

        return trim(implode("\n", $lines));
    }

    /**
     * @param array{id?: int|null, username?: string|null, display_name?: string|null}|null $author
     */
    private function authorLabel(?array $author): string
    {
        if ($author === null || empty($author['display_name'])) {
            return Entity::DELETED_LABEL;
        }

        return ! empty($author['username'])
            ? $author['display_name'].' (@'.$author['username'].')'
            : (string) $author['display_name'];
    }
}
