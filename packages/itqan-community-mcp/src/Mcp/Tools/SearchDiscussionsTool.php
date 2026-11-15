<?php

declare(strict_types=1);

namespace Itqan\CommunityMcp\Mcp\Tools;

use Flarum\User\User;
use Itqan\CommunityMcp\Search\ForumSearchService;
use Itqan\CommunityMcp\Support\Entity;

/**
 * `search_discussions` — keyword/tag/author search across public discussions.
 */
class SearchDiscussionsTool implements ToolInterface
{
    private ForumSearchService $search;

    public function __construct(ForumSearchService $search)
    {
        $this->search = $search;
    }

    public function getName(): string
    {
        return 'search_discussions';
    }

    public function getDescription(): string
    {
        return 'Search public discussions in Itqan Community (مجتمع إتقان) by Arabic or '
            .'English keywords, category/topic tag slug, and author username. Use short '
            .'keywords (1-4 words) rather than a full question. Results include the '
            .'discussion URL, author, date, tags, engagement (replies, participants, '
            .'votes) and a matching snippet.';
    }

    public function getInputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'query' => [
                    'type' => 'string',
                    'description' => 'Short keywords to match in discussion titles and post bodies, e.g. "ASR" or "الرسم العثماني". Omit to browse by tag/author only.',
                ],
                'tag' => [
                    'type' => 'string',
                    'description' => 'Tag slug filter, e.g. "ai", "asr", "standards", "quranic-data", "imad". Call `list_tags` for the full list.',
                ],
                'author' => [
                    'type' => 'string',
                    'description' => 'Filter by discussion author username (with or without a leading @).',
                ],
                'sort' => [
                    'type' => 'string',
                    'enum' => ['relevance', 'latest', 'top', 'newest', 'oldest'],
                    'description' => 'relevance (default, title match + sticky/votes first), latest (last activity), top (most replies), newest or oldest (creation date).',
                    'default' => 'relevance',
                ],
                'limit' => [
                    'type' => 'integer',
                    'minimum' => 1,
                    'maximum' => 25,
                    'description' => 'Maximum number of discussions to return (default 10, server cap may be lower).',
                    'default' => 10,
                ],
                'offset' => [
                    'type' => 'integer',
                    'minimum' => 0,
                    'description' => 'Pagination offset (number of results to skip).',
                    'default' => 0,
                ],
            ],
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
        $query = $this->stringArgument($arguments, 'query');
        $tag = $this->stringArgument($arguments, 'tag');
        $author = $this->stringArgument($arguments, 'author');
        $sort = $this->stringArgument($arguments, 'sort') ?? 'relevance';
        $limit = $this->intArgument($arguments, 'limit', 10);
        $offset = $this->intArgument($arguments, 'offset', 0);

        $result = $this->search->searchDiscussions($actor, $query, $tag, $author, $sort, $limit, $offset);

        return $this->render($result);
    }

    /**
     * @param array<string, mixed> $result
     */
    public function render(array $result): string
    {
        $results = $result['results'] ?? [];
        $query = $result['query'] ?? null;
        $tag = $result['tag'] ?? null;
        $author = $result['author'] ?? null;
        $sort = (string) ($result['sort'] ?? 'relevance');
        $offset = (int) ($result['offset'] ?? 0);
        $limit = (int) ($result['limit'] ?? 10);
        $hasMore = (bool) ($result['has_more'] ?? false);

        $lines = [];
        $lines[] = '# البحث في المناقشات (Discussion Search)';
        $lines[] = '';

        $filters = [];
        $filters[] = '**الاستعلام (Query):** '.($query !== null ? '«'.$query.'»' : '—');
        $filters[] = '**الوسم (Tag):** '.($tag !== null ? '`'.$tag.'`' : '—');
        $filters[] = '**الكاتب (Author):** '.($author !== null ? '@'.$author : '—');
        $filters[] = '**الترتيب (Sort):** '.$this->sortLabel($sort);
        $filters[] = '**النتائج (Results):** '.count($results);
        $filters[] = '**الإزاحة (Offset):** '.$offset;

        $lines[] = implode(' · ', $filters);

        if (! empty($result['expanded'])) {
            $lines[] = '';
            $lines[] = '> تم توسيع البحث تلقائيًا بمتغيرات إملائية عربية (تشكيل، همزات، تاء مربوطة) للعثور على نتائج إضافية.';
        }

        $lines[] = '';

        if ($results === []) {
            $lines[] = '_لم يتم العثور على مناقشات مطابقة (No matching discussions)._';
            $lines[] = '';
            $lines[] = 'اقتراحات (Suggestions):';
            $lines[] = '- استخدم كلمات مفتاحية أقصر (1-4 كلمات) أو مرادفًا عربيًا/إنجليزيًا.';
            $lines[] = '- استدعِ `list_tags` للتأكد من `slug` الوسم، ثم أعد البحث بدون كلمة مفتاحية لعرض مناقشات القسم.';
            $lines[] = '- جرّب `search_posts` للبحث داخل الردود المتشعبة.';

            return implode("\n", $lines);
        }

        foreach ($results as $index => $discussion) {
            $lines[] = '---';
            $lines[] = '';
            $lines[] = sprintf('## %d. %s', $index + 1, $discussion['title']);

            $lines[] = '';
            $lines[] = '- **الرابط (URL):** '.$discussion['url'];
            $lines[] = '- **معرف المناقشة (Discussion ID):** '.$discussion['id'];
            $lines[] = '- **الكاتب (Author):** '.$this->authorLabel($discussion['author'] ?? null);

            if (! empty($discussion['created_at'])) {
                $lines[] = '- **تاريخ الإنشاء (Created):** '.$discussion['created_at'];
            }

            if (! empty($discussion['last_posted_at'])) {
                $lines[] = '- **آخر نشاط (Last activity):** '.$discussion['last_posted_at'];
            }

            if (! empty($discussion['is_sticky'])) {
                $lines[] = '- **مثبّتة (Sticky):** نعم 📌';
            }

            if (! empty($discussion['detected_lang'])) {
                $lines[] = '- **اللغة (Language):** `'.$discussion['detected_lang'].'`';
            }

            $lines[] = '- **الوسوم (Tags):** '.$this->tagLine($discussion['tags'] ?? []);
            $lines[] = '- **التفاعل (Engagement):** '.$this->engagementLine($discussion);

            if (! empty($discussion['snippet'])) {
                $lines[] = '- **المقتطف (Snippet):**';
                $lines[] = '';

                foreach (explode("\n", (string) $discussion['snippet']) as $snippetLine) {
                    $lines[] = '  > '.$snippetLine;
                }
            }

            if (! empty($discussion['matched_post_url'])
                && (int) ($discussion['matched_post_number'] ?? 0) > 1) {
                $lines[] = '';
                $lines[] = '- **مشاركة مطابقة (Matched post):** '.$discussion['matched_post_url'];
            }

            $lines[] = '';
        }

        if ($hasMore) {
            $lines[] = '---';
            $lines[] = '';
            $lines[] = sprintf(
                '**المزيد (More):** توجد نتائج إضافية. استدعِ `search_discussions` مرة أخرى مع `offset=%d` و`limit=%d`.',
                $offset + max(1, $limit),
                $limit
            );
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

    /**
     * @param array<int, array{slug: string, name: string}> $tags
     */
    private function tagLine(array $tags): string
    {
        if ($tags === []) {
            return '—';
        }

        $parts = [];

        foreach ($tags as $tag) {
            $parts[] = $tag['name'] !== '' ? '`'.$tag['slug'].'` ('.$tag['name'].')' : '`'.$tag['slug'].'`';
        }

        return implode(', ', $parts);
    }

    /**
     * @param array<string, mixed> $discussion
     */
    private function engagementLine(array $discussion): string
    {
        $parts = [];
        $parts[] = 'الردود (Replies): '.(int) ($discussion['reply_count'] ?? 0);
        $parts[] = 'المشاركون (Participants): '.(int) ($discussion['participant_count'] ?? 0);

        if ((int) ($discussion['votes'] ?? 0) > 0) {
            $parts[] = '👍 الأصوات (Votes): '.(int) $discussion['votes'];
        }

        if ((int) ($discussion['likes_count'] ?? 0) > 0) {
            $parts[] = '❤️ الإعجابات (Likes): '.(int) $discussion['likes_count'];
        }

        return implode(' · ', $parts);
    }

    private function sortLabel(string $sort): string
    {
        return match ($sort) {
            'latest' => 'الأحدث نشاطًا (latest)',
            'top' => 'الأكثر تفاعلًا (top)',
            'newest' => 'الأحدث إنشاءً (newest)',
            'oldest' => 'الأقدم (oldest)',
            default => 'الأكثر صلة (relevance)',
        };
    }

    /**
     * @param array<string, mixed> $arguments
     */
    private function stringArgument(array $arguments, string $key): ?string
    {
        if (! array_key_exists($key, $arguments) || ! is_scalar($arguments[$key])) {
            return null;
        }

        $value = trim((string) $arguments[$key]);

        return $value !== '' ? $value : null;
    }

    /**
     * @param array<string, mixed> $arguments
     */
    private function intArgument(array $arguments, string $key, int $default): int
    {
        if (! array_key_exists($key, $arguments) || ! is_numeric($arguments[$key])) {
            return $default;
        }

        return (int) $arguments[$key];
    }
}
