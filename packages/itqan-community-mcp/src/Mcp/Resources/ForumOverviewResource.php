<?php

declare(strict_types=1);

namespace Itqan\CommunityMcp\Mcp\Resources;

use Flarum\Settings\SettingsRepositoryInterface;
use Flarum\User\User;
use Itqan\CommunityMcp\Search\ForumSearchService;

/**
 * `itqan://overview` — orientation for a model that just connected.
 *
 * Live statistics and highlighted discussions are read through the same
 * visibility-scoped service the tools use, so private or hidden content never
 * appears in the overview.
 */
class ForumOverviewResource
{
    public const URI = 'itqan://overview';

    private ForumSearchService $search;

    private SettingsRepositoryInterface $settings;

    public function __construct(ForumSearchService $search, SettingsRepositoryInterface $settings)
    {
        $this->search = $search;
        $this->settings = $settings;
    }

    /**
     * @return array{uri: string, name: string, description: string, mimeType: string}
     */
    public function toResourceDefinition(): array
    {
        return [
            'uri' => self::URI,
            'name' => 'Itqan Community Overview (نظرة عامة على مجتمع إتقان)',
            'description' => 'Forum title and description, live public statistics, the MCP tool guide, sticky and recent discussions, and citation rules.',
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
        $baseUrl = $this->search->getBaseUrl();
        $title = $this->setting('forum_title', 'مجتمع إتقان (Itqan Community)');
        $description = $this->setting('forum_description', '');
        $stats = $this->search->getForumStatistics($actor);

        $lines = [];
        $lines[] = '# '.$title;
        $lines[] = '';

        if ($description !== '') {
            $lines[] = $description;
            $lines[] = '';
        }

        $lines[] = 'مجتمع عربي أولاً يعنى بتقنيات القرآن الكريم والذكاء الاصطناعي والتعرف على الكلام والمعايير المفتوحة.';
        $lines[] = '';
        $lines[] = '- **الموقع (Site):** '.$baseUrl;
        $lines[] = '- **المناقشات العامة (Public discussions):** '.number_format($stats['discussions']);
        $lines[] = '- **المشاركات (Posts):** '.number_format($stats['posts']);
        $lines[] = '- **الأقسام والوسوم (Tags):** '.number_format($stats['tags']);
        $lines[] = '- **الأعضاء (Members):** '.number_format($stats['users']);
        $lines[] = '';
        $lines[] = '## أدوات MCP المتاحة (Available MCP tools)';
        $lines[] = '';
        $lines[] = '| الأداة (Tool) | الاستخدام (Use) |';
        $lines[] = '| --- | --- |';
        $lines[] = '| `search_discussions` | البحث في عناوين المناقشات ومحتواها بكلمات مفتاحية، مع تصفية بالوسم أو الكاتب. |';
        $lines[] = '| `search_posts` | البحث داخل المشاركات والردود المتشعبة والعثور على تعليق بعينه. |';
        $lines[] = '| `get_discussion` | قراءة مناقشة كاملة مع شجرة الردود والتصويتات وروابط مباشرة. |';
        $lines[] = '| `list_tags` | عرض الأقسام الرئيسية والوسوم الموضوعية ومعرّفاتها (`slug`). |';
        $lines[] = '| `get_user_contributions` | ملف عضو ونشاطه الأخير من مناقشات وردود. |';
        $lines[] = '';
        $lines[] = '## الموارد والطلبات المحفوظة (Resources & prompts)';
        $lines[] = '';
        $lines[] = '- `itqan://overview` — هذه النظرة العامة.';
        $lines[] = '- `itqan://tags` — خريطة الأقسام والوسوم الكاملة.';
        $lines[] = '- `research_quranic_tech_topic` — طلب بحث معمّق حول موضوع تقني قرآني.';

        $sticky = $this->search->hasColumn('discussions', 'is_sticky')
            ? $this->search->getStickyDiscussions($actor, 5)
            : [];

        if ($sticky !== []) {
            $lines[] = '';
            $lines[] = '## المناقشات المثبتة (Sticky discussions)';
            $lines[] = '';
            $lines = array_merge($lines, $this->discussionLines($sticky));
        }

        $recent = $this->search->getRecentDiscussions($actor, 7);

        if ($recent !== []) {
            $lines[] = '';
            $lines[] = '## أحدث المناقشات (Recent discussions)';
            $lines[] = '';
            $lines = array_merge($lines, $this->discussionLines($recent));
        }

        $lines[] = '';
        $lines[] = '## نصائح البحث والاقتباس (Search & citation tips)';
        $lines[] = '';
        $lines[] = '- استخدم كلمات مفتاحية قصيرة (1-4 كلمات) بدل السؤال الكامل، وابحث بالعربية والإنجليزية كلٌّ على حدة.';
        $lines[] = '- استدعِ `list_tags` أولاً لمعرفة `slug` القسم أو الوسم المناسب قبل التصفية.';
        $lines[] = '- اقرأ الردود المتشعبة كحوار: كل مشاركة تبيّن المشاركة التي تردّ عليها.';
        $lines[] = '- الأصوات `votes` مؤشر على تقدير المجتمع لا دليل على صحة المعلومة.';
        $lines[] = '- اقتبس دائمًا بالعنوان والكاتب والرابط المباشر: `'.$baseUrl.'/d/{discussion_id}/{post_number}`.';
        $lines[] = '- لا تنسب إلى المجتمع ما ورد في مشاركة محذوفة أو غير معتمدة.';

        return trim(implode("\n", $lines));
    }

    /**
     * @param array<int, array<string, mixed>> $discussions
     * @return array<int, string>
     */
    private function discussionLines(array $discussions): array
    {
        $lines = [];

        foreach ($discussions as $discussion) {
            $author = $discussion['author'] ?? [];
            $authorName = $author['display_name'] ?? '—';
            $username = $author['username'] ?? null;

            $line = sprintf(
                '- **[%s](%s)** — %s%s',
                $discussion['title'],
                $discussion['url'],
                $authorName,
                $username !== null ? ' (@'.$username.')' : ''
            );

            if (! empty($discussion['created_at'])) {
                $line .= ' · '.$discussion['created_at'];
            }

            $line .= ' · الردود: '.(int) ($discussion['reply_count'] ?? 0);

            if ((int) ($discussion['votes'] ?? 0) > 0) {
                $line .= ' · 👍 '.(int) $discussion['votes'];
            }

            $lines[] = $line;
        }

        return $lines;
    }

    private function setting(string $key, string $default): string
    {
        try {
            $value = $this->settings->get($key, $default);

            return is_string($value) && trim($value) !== '' ? trim($value) : $default;
        } catch (\Throwable) {
            return $default;
        }
    }
}
