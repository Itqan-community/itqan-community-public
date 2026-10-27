<?php

declare(strict_types=1);

namespace Itqan\CommunityMcp\Mcp\Tools;

use Flarum\User\User;
use Itqan\CommunityMcp\Search\ForumSearchService;
use Itqan\CommunityMcp\Support\Entity;

/**
 * `list_tags` — the community's category and topic-tag taxonomy.
 *
 * Two levels are exposed, because Itqan Community uses both: primary categories
 * carry a `position` (and may have child categories such as `imad` under
 * `active`), while secondary topic tags have no position and are used purely as
 * search filters.
 */
class ListTagsTool implements ToolInterface
{
    private ForumSearchService $search;

    public function __construct(ForumSearchService $search)
    {
        $this->search = $search;
    }

    public function getName(): string
    {
        return 'list_tags';
    }

    public function getDescription(): string
    {
        return 'List Itqan Community (مجتمع إتقان) categories and topic tags with their '
            .'slugs, Arabic names, discussion counts and descriptions. Call this before '
            .'filtering `search_discussions` by tag; primary categories are grouped '
            .'with their child categories, secondary topic tags are listed separately.';
    }

    public function getInputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'include_empty' => [
                    'type' => 'boolean',
                    'description' => 'Include tags that currently have no discussions. Defaults to true.',
                    'default' => true,
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
        $includeEmpty = true;

        if (array_key_exists('include_empty', $arguments)) {
            $parsed = filter_var($arguments['include_empty'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);

            if ($parsed !== null) {
                $includeEmpty = $parsed;
            }
        }

        return $this->render($this->search->getTagsHierarchy($includeEmpty, $actor));
    }

    /**
     * Render an already-built hierarchy. Shared with `ForumTaxonomyResource`.
     *
     * @param array{primary?: array<int, array<string, mixed>>, secondary?: array<int, array<string, mixed>>, count?: int} $hierarchy
     */
    public function render(array $hierarchy): string
    {
        $primary = $hierarchy['primary'] ?? [];
        $secondary = $hierarchy['secondary'] ?? [];
        $total = (int) ($hierarchy['count'] ?? 0);

        $lines = [];
        $lines[] = '# أقسام ووسوم مجتمع إتقان (Categories & Tags)';
        $lines[] = '';
        $lines[] = '**إجمالي الأقسام والوسوم (Total tags):** '.number_format($total);
        $lines[] = '';
        $lines[] = 'استخدم قيمة `slug` في المعامل `tag` عند استدعاء `search_discussions`.';
        $lines[] = '';

        // ------------------------------------------------------------------
        // Primary categories
        // ------------------------------------------------------------------
        $lines[] = '## الأقسام الرئيسية (Primary Categories)';
        $lines[] = '';

        if ($primary === []) {
            $lines[] = '_لا توجد أقسام رئيسية متاحة حاليًا (No primary categories available)._';
            $lines[] = '';
        } else {
            foreach ($primary as $index => $tag) {
                $lines[] = sprintf('### %d. %s (`%s`)', $index + 1, $tag['name'], $tag['slug']);
                $lines[] = '';
                $lines[] = '- **المعرف (Slug):** `'.$tag['slug'].'`';
                $lines[] = '- **المناقشات (Discussions):** '.number_format((int) $tag['discussion_count']);
                $lines[] = '- **الوصف (Description):** '.Entity::orDash((string) ($tag['description'] ?? ''));

                $children = $tag['children'] ?? [];

                if ($children !== []) {
                    $lines[] = '- **أقسام فرعية (Sub-categories):**';

                    foreach ($children as $child) {
                        $lines[] = sprintf(
                            '  - **%s** (`%s`) — %s مناقشة — %s',
                            $child['name'],
                            $child['slug'],
                            number_format((int) $child['discussion_count']),
                            Entity::orDash((string) ($child['description'] ?? ''))
                        );
                    }
                }

                $lines[] = '';
            }
        }

        // ------------------------------------------------------------------
        // Secondary topic tags
        // ------------------------------------------------------------------
        $lines[] = '## الوسوم الموضوعية (Secondary Topic Tags)';
        $lines[] = '';

        if ($secondary === []) {
            $lines[] = '_لا توجد وسوم موضوعية متاحة حاليًا (No secondary topic tags available)._';
        } else {
            $lines[] = '| الوسم (Slug) | الاسم (Name) | المناقشات (Discussions) | الوصف (Description) |';
            $lines[] = '| --- | --- | ---: | --- |';

            foreach ($secondary as $tag) {
                $lines[] = sprintf(
                    '| `%s` | %s | %s | %s |',
                    $tag['slug'],
                    $this->cell($tag['name']),
                    number_format((int) $tag['discussion_count']),
                    $this->cell(Entity::orDash((string) ($tag['description'] ?? '')))
                );
            }
        }

        $lines[] = '';

        return trim(implode("\n", $lines));
    }

    /**
     * Keep a Markdown table cell on one row and escape its separator.
     */
    private function cell(string $value): string
    {
        $value = preg_replace('/\s*\R\s*/u', ' ', $value) ?? $value;

        return str_replace('|', '\|', trim($value));
    }
}
