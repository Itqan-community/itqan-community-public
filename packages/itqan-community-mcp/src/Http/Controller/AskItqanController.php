<?php

declare(strict_types=1);

namespace Itqan\CommunityMcp\Http\Controller;

use Flarum\Http\RequestUtil;
use Flarum\User\Guest;
use Flarum\User\User;
use Itqan\CommunityMcp\Http\CorsHeaders;
use Itqan\CommunityMcp\Search\ForumSearchService;
use Laminas\Diactoros\Response\TextResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * «إسأل إتقان .. Ask Itqan» Smart Assistant Endpoint.
 *
 * Implements the RAG assistant proposed in discussion #947:
 * - Searches community discussions and posts for previous experiences and insights.
 * - Enforces the strict rule: "No answer without a direct, dated link to its source."
 * - Returns "لم أجد" if no relevant discussions exist.
 * - Formats grounded summaries with permalinks, authors, and dates.
 */
class AskItqanController implements RequestHandlerInterface
{
    private ForumSearchService $searchService;

    public function __construct(ForumSearchService $searchService)
    {
        $this->searchService = $searchService;
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $method = strtoupper($request->getMethod());

        if ($method === 'OPTIONS') {
            return CorsHeaders::apply(new TextResponse('', 204));
        }

        $actor = $this->actor($request);
        $query = $this->extractQuery($request);

        if ($query === '') {
            return $this->jsonResponse(200, [
                'query' => '',
                'found' => false,
                'answer' => 'يرجى كتابة سؤالك أو استفسارك للبحث في خبرات ونقاشات المجتمع.',
                'discussions' => [],
                'posts' => [],
            ]);
        }

        // Search discussions by relevance
        $discussionResults = $this->searchService->searchDiscussions(
            $actor,
            query: $query,
            limit: 5,
            sort: 'relevance'
        );

        $discussions = $discussionResults['results'] ?? [];

        // Also search individual posts for precise mentions / technical snippets
        $postResults = $this->searchService->searchPosts(
            $actor,
            query: $query,
            limit: 5
        );

        $posts = $postResults['results'] ?? [];

        if (empty($discussions) && empty($posts)) {
            return $this->jsonResponse(200, [
                'query' => $query,
                'found' => false,
                'answer' => "لم أجد في نقاشات ومشاركات المجتمع السابقة ما يطابق بحثك بدقة.\n\nيمكنك طرح هذا السؤال كموضوع جديد في المجتمع ليشاركك المطورون والباحثون تجاربهم.",
                'discussions' => [],
                'posts' => [],
            ]);
        }

        $answer = $this->synthesizeAnswer($query, $discussions, $posts);

        return $this->jsonResponse(200, [
            'query' => $query,
            'found' => true,
            'answer' => $answer,
            'discussions' => $discussions,
            'posts' => $posts,
        ]);
    }

    private function extractQuery(ServerRequestInterface $request): string
    {
        $params = $request->getQueryParams();
        $query = $params['q'] ?? $params['query'] ?? null;

        if ($query === null && $request->getMethod() === 'POST') {
            $parsed = $request->getParsedBody();
            if (is_array($parsed)) {
                $query = $parsed['q'] ?? $parsed['query'] ?? null;
            } else {
                $raw = (string) $request->getBody();
                if ($raw !== '') {
                    $json = json_decode($raw, true);
                    if (is_array($json)) {
                        $query = $json['q'] ?? $json['query'] ?? null;
                    }
                }
            }
        }

        if (! is_string($query)) {
            return '';
        }

        $trimmed = trim($query);

        if (mb_strlen($trimmed, 'UTF-8') > ForumSearchService::MAX_QUERY_LENGTH) {
            $trimmed = mb_substr($trimmed, 0, ForumSearchService::MAX_QUERY_LENGTH, 'UTF-8');
        }

        return $trimmed;
    }

    /**
     * Synthesize a grounded answer with citations and direct permalinks.
     *
     * @param array<int, array<string, mixed>> $discussions
     * @param array<int, array<string, mixed>> $posts
     */
    private function synthesizeAnswer(string $query, array $discussions, array $posts): string
    {
        $lines = [];
        $lines[] = "بناءً على نقاشات وتجارب مجتمع إتقان حول **«{$query}»**، إليك أبرز ما طُرح ومصادره الموثقة:";
        $lines[] = '';

        $seenDiscussionIds = [];

        foreach ($discussions as $disc) {
            $id = $disc['id'] ?? 0;
            $title = $disc['title'] ?? 'مناقشة';
            $url = $disc['url'] ?? "https://community.itqan.dev/d/{$id}";
            $author = $disc['author']['display_name'] ?? $disc['author']['username'] ?? 'عضو إتقان';
            $date = $disc['created_at'] ?? 'غير محدد';
            $snippet = trim($disc['snippet'] ?? '');
            $replyCount = $disc['reply_count'] ?? 0;

            $seenDiscussionIds[$id] = true;

            $lines[] = "### 📌 [{$title}]({$url})";
            $lines[] = "- **الكاتب:** {$author} | **التاريخ:** {$date} | **الردود:** {$replyCount}";

            if ($snippet !== '') {
                // Keep snippet clean and brief
                $cleanSnippet = preg_replace('/\s+/', ' ', $snippet);
                $lines[] = "> {$cleanSnippet}";
            }

            $lines[] = '';
        }

        // Highlight matching comments if from different discussions
        $additionalPosts = [];
        foreach ($posts as $post) {
            $discId = $post['discussion_id'] ?? 0;
            if (! isset($seenDiscussionIds[$discId])) {
                $additionalPosts[] = $post;
            }
        }

        if (! empty($additionalPosts)) {
            $lines[] = '#### 💬 مداخلات إضافية ذات صلة:';
            foreach (array_slice($additionalPosts, 0, 3) as $post) {
                $postUrl = $post['url'] ?? '#';
                $discTitle = $post['discussion_title'] ?? 'مشاركة';
                $author = $post['author']['display_name'] ?? $post['author']['username'] ?? 'عضو';
                $date = $post['created_at'] ?? '';
                $lines[] = "- [{$discTitle}]({$postUrl}) — بواسطة **{$author}** ({$date})";
            }
            $lines[] = '';
        }

        $lines[] = '---';
        $lines[] = '💡 *جميع المعلومات أعلاه مستخلصة مباشرة من أرشيف المجتمع مع الروابط للرجوع إلى السياق الأصلي والتفاعل مع أصحابه.*';

        return implode("\n", $lines);
    }

    private function actor(ServerRequestInterface $request): User
    {
        try {
            if (class_exists(RequestUtil::class) && method_exists(RequestUtil::class, 'getActor')) {
                return RequestUtil::getActor($request);
            }
        } catch (\Throwable) {
            // Fall through
        }

        $actor = $request->getAttribute('actor');

        if ($actor instanceof User) {
            return $actor;
        }

        return new Guest();
    }

    /**
     * @param array<string, mixed> $payload
     * @param array<string, string> $headers
     */
    private function jsonResponse(int $status, array $payload, array $headers = []): ResponseInterface
    {
        $flags = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;
        $body = json_encode($payload, $flags);

        if ($body === false) {
            $body = '{"error":"JSON encoding failed"}';
            $status = 500;
        }

        $headers = array_merge([
            'Content-Type' => 'application/json; charset=utf-8',
            'Cache-Control' => 'no-store',
            'X-Content-Type-Options' => 'nosniff',
        ], $headers);

        return CorsHeaders::apply(new TextResponse($body, $status, $headers));
    }
}
