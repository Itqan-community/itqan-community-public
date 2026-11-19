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
 * Prior-art suggestions helper ("هل اطلعت على هذا؟").
 *
 * Checks existing discussions while a member is typing a title in the composer,
 * helping prevent duplicate threads before they are published.
 */
class SimilarDiscussionsController implements RequestHandlerInterface
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

        $params = $request->getQueryParams();
        $title = trim((string) ($params['title'] ?? $params['q'] ?? ''));

        // Only search if the title is at least 3 characters long
        if (mb_strlen($title, 'UTF-8') < 3) {
            return $this->jsonResponse(200, [
                'matches' => [],
            ]);
        }

        $actor = $this->actor($request);

        $results = $this->searchService->searchDiscussions(
            $actor,
            query: $title,
            limit: 3,
            sort: 'relevance'
        );

        $matches = [];
        foreach ($results['results'] ?? [] as $disc) {
            $matches[] = [
                'id' => $disc['id'] ?? 0,
                'title' => $disc['title'] ?? '',
                'url' => $disc['url'] ?? '',
                'created_at' => $disc['created_at'] ?? '',
                'reply_count' => $disc['reply_count'] ?? 0,
                'author' => $disc['author']['display_name'] ?? $disc['author']['username'] ?? '',
            ];
        }

        return $this->jsonResponse(200, [
            'matches' => $matches,
        ]);
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
