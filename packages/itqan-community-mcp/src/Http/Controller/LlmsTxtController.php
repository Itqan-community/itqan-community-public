<?php

declare(strict_types=1);

namespace Itqan\CommunityMcp\Http\Controller;

use Flarum\User\Guest;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Itqan\CommunityMcp\Search\ForumSearchService;
use Laminas\Diactoros\Response\TextResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * `GET /llms.txt` — the llms.txt discovery file for Itqan Community.
 *
 * Generated from live public data (tags, sticky and recent discussions) and
 * cached for one hour. Only content visible to guests is included, so no
 * private tag or hidden discussion can leak into the public file.
 */
class LlmsTxtController implements RequestHandlerInterface
{
    public const CACHE_KEY = 'itqan_mcp_llms_txt_v2';

    public const CACHE_TTL = 3600;

    private ForumSearchService $search;

    private CacheRepository $cache;

    public function __construct(ForumSearchService $searchService, CacheRepository $cache)
    {
        $this->search = $searchService;
        $this->cache = $cache;
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        return new TextResponse($this->content(), 200, [
            'Content-Type' => 'text/plain; charset=utf-8',
            'Cache-Control' => 'public, max-age=3600',
            'Access-Control-Allow-Origin' => '*',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function content(): string
    {
        try {
            return $this->cache->remember(
                self::CACHE_KEY,
                self::CACHE_TTL,
                fn (): string => $this->build()
            );
        } catch (\Throwable) {
            return $this->build();
        }
    }

    private function build(): string
    {
        $actor = new Guest();
        $baseUrl = $this->search->getBaseUrl();

        $lines = [];
        $lines[] = '# مجتمع إتقان لتقنيات القرآن (Itqan Community)';
        $lines[] = '';
        $lines[] = '> مجتمع عربي أولاً يعنى بتقنيات القرآن الكريم والذكاء الاصطناعي والتعرف على الكلام '
            .'وبيانات القرآن والمعايير المفتوحة. Arabic-first community for Quranic technology, AI, '
            .'speech recognition, Quranic data and open standards.';
        $lines[] = '';
        $lines[] = 'الموقع (Site): '.$baseUrl;
        $lines[] = '';

        // ------------------------------------------------------------------
        // AI / MCP integration
        // ------------------------------------------------------------------
        $lines[] = '## الوصول عبر الذكاء الاصطناعي (AI & MCP Integration)';
        $lines[] = '';
        $lines[] = '- **MCP server (JSON-RPC 2.0, Stateless Streamable HTTP):** '.$baseUrl.'/api/mcp';
        $lines[] = '- **MCP discovery document (GET):** '.$baseUrl.'/api/mcp';
        $lines[] = '- **Single discussion as Markdown:** '.$baseUrl.'/api/mcp/discussions/{id}.md '
            .'(also works without the `.md` suffix: '.$baseUrl.'/api/mcp/discussions/{id})';
        $lines[] = '- **Agent skill file:** '.$baseUrl.'/api/mcp/SKILL.md';
        $lines[] = '- **Protocol versions:** 2025-06-18, 2025-03-26, 2024-11-05 '
            .'(unsupported client versions fall back to 2025-03-26).';
        $lines[] = '';
        $lines[] = 'الأدوات المتاحة عبر MCP (MCP tools): `list_tags`, `search_discussions`, '
            .'`get_discussion`, `search_posts`, `get_user_contributions`.';
        $lines[] = 'الموارد (Resources): `itqan://overview`, `itqan://tags`. '
            .'الطلبات المحفوظة (Prompts): `research_quranic_tech_topic`.';
        $lines[] = '';

        // ------------------------------------------------------------------
        // Tags
        // ------------------------------------------------------------------
        $lines[] = '## الأقسام والوسوم الرئيسية (Tags)';
        $lines[] = '';

        $hierarchy = $this->search->getTagsHierarchy(true);
        $primary = $hierarchy['primary'] ?? [];
        $secondary = $hierarchy['secondary'] ?? [];

        if ($primary === [] && $secondary === []) {
            $lines[] = '_لا توجد أقسام أو وسوم عامة حاليًا (No public tags available)._';
            $lines[] = '';
        } else {
            foreach ($primary as $tag) {
                $lines[] = sprintf(
                    '- **%s** (`%s`) — %s — %s',
                    $tag['name'],
                    $tag['slug'],
                    $baseUrl.'/t/'.$tag['slug'],
                    number_format((int) ($tag['discussion_count'] ?? 0)).' مناقشة'
                );

                foreach ($tag['children'] ?? [] as $child) {
                    $lines[] = sprintf(
                        '  - %s (`%s`) — %s — %s',
                        $child['name'],
                        $child['slug'],
                        $baseUrl.'/t/'.$child['slug'],
                        number_format((int) ($child['discussion_count'] ?? 0)).' مناقشة'
                    );
                }
            }

            if ($secondary !== []) {
                $lines[] = '';
                $lines[] = '**وسوم موضوعية (Topic tags):** '
                    .implode(', ', array_map(
                        static fn (array $tag): string => '`'.$tag['slug'].'`',
                        $secondary
                    ));
            }

            $lines[] = '';
        }

        // ------------------------------------------------------------------
        // Featured / recent discussions
        // ------------------------------------------------------------------
        $lines[] = '## أحدث النقاشات المميزة (Featured & Recent Discussions)';
        $lines[] = '';

        $discussions = [];

        foreach ($this->search->getStickyDiscussions($actor, 5) as $discussion) {
            $discussions[(int) ($discussion['id'] ?? 0)] = $discussion;
        }

        foreach ($this->search->getRecentDiscussions($actor, 8) as $discussion) {
            $id = (int) ($discussion['id'] ?? 0);

            if ($id > 0 && ! array_key_exists($id, $discussions)) {
                $discussions[$id] = $discussion;
            }
        }

        if ($discussions === []) {
            $lines[] = '_لا توجد مناقشات عامة حديثة (No recent public discussions)._';
            $lines[] = '';
        } else {
            foreach ($discussions as $id => $discussion) {
                $author = $discussion['author'] ?? [];
                $authorName = $author['display_name'] ?? '—';
                $username = $author['username'] ?? null;
                $url = $discussion['url'] ?? $baseUrl.'/d/'.$id;

                $line = sprintf('- [%s](%s)', $discussion['title'] ?? ('مناقشة #'.$id), $url);

                if (! empty($discussion['is_sticky'])) {
                    $line .= ' 📌';
                }

                $line .= ' — '.$authorName.($username !== null ? ' (@'.$username.')' : '');
                $line .= ' — الردود: '.(int) ($discussion['reply_count'] ?? 0);

                $lines[] = $line;
                $lines[] = '  - Markdown: '.$baseUrl.'/api/mcp/discussions/'.$id.'.md';
            }

            $lines[] = '';
        }

        // ------------------------------------------------------------------
        // Citation guidance
        // ------------------------------------------------------------------
        $lines[] = '## القواعد والإرشادات (Guidelines)';
        $lines[] = '';
        $lines[] = '- استخدم كلمات مفتاحية قصيرة (1-4 كلمات) في البحث، لا أسئلة كاملة.';
        $lines[] = '- استدعِ `list_tags` لمعرفة `slug` القسم أو الوسم قبل التصفية.';
        $lines[] = '- اقرأ الردود المتشعبة كحوار؛ كل مشاركة تبيّن المشاركة التي تردّ عليها.';
        $lines[] = '- اقتبس دائمًا بالعنوان والكاتب والرابط المباشر: `'.$baseUrl.'/d/{discussion_id}/{post_number}`.';
        $lines[] = '- الأصوات `votes` مؤشر على تقدير المجتمع لا دليل على صحة المعلومة.';
        $lines[] = '- لا تنسب إلى المجتمع ما ورد في مشاركة محذوفة أو غير معتمدة.';
        $lines[] = '';

        return implode("\n", $lines);
    }
}
