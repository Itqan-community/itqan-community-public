<?php

declare(strict_types=1);

namespace Itqan\CommunityMcp\Mcp\Resources;

use Flarum\User\User;
use Itqan\CommunityMcp\Mcp\Tools\ListTagsTool;
use Itqan\CommunityMcp\Search\ForumSearchService;

/**
 * `itqan://tags` — the complete category and topic-tag taxonomy.
 *
 * The Markdown is produced by `ListTagsTool::render()`, so the resource and the
 * `list_tags` tool can never drift apart.
 */
class ForumTaxonomyResource
{
    public const URI = 'itqan://tags';

    private ForumSearchService $search;

    private ListTagsTool $listTagsTool;

    public function __construct(ForumSearchService $search, ListTagsTool $listTagsTool)
    {
        $this->search = $search;
        $this->listTagsTool = $listTagsTool;
    }

    /**
     * @return array{uri: string, name: string, description: string, mimeType: string}
     */
    public function toResourceDefinition(): array
    {
        return [
            'uri' => self::URI,
            'name' => 'Itqan Community Tag Taxonomy (خريطة الأقسام والوسوم)',
            'description' => 'Every primary category (with its child categories) and secondary topic tag, including slug, Arabic name, discussion count and description.',
            'mimeType' => 'text/markdown',
        ];
    }

    /**
     * @return array{uri: string, mimeType: string, text: string}
     */
    public function read(User $actor): array
    {
        return [
            'uri' => self::URI,
            'mimeType' => 'text/markdown',
            'text' => $this->render($actor),
        ];
    }

    public function render(User $actor): string
    {
        return $this->listTagsTool->render($this->search->getTagsHierarchy(true, $actor));
    }
}
