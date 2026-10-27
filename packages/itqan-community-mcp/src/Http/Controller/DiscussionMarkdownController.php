<?php

declare(strict_types=1);

namespace Itqan\CommunityMcp\Http\Controller;

use Flarum\User\Guest;
use Flarum\User\User;
use Itqan\CommunityMcp\Http\CorsHeaders;
use Itqan\CommunityMcp\Mcp\Tools\GetDiscussionTool;
use Laminas\Diactoros\Response\TextResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * `GET /api/mcp/discussions/{id}` and `/api/mcp/discussions/{id}.md`.
 *
 * Returns the same Markdown as the `get_discussion` tool, so a model that only
 * has plain HTTP access (no MCP client) can still read a full thread, including
 * the branched reply tree and pagination footer.
 */
class DiscussionMarkdownController implements RequestHandlerInterface
{
    private const DEFAULT_LIMIT = 25;

    private const MAX_LIMIT = 50;

    private GetDiscussionTool $getDiscussionTool;

    public function __construct(GetDiscussionTool $getDiscussionTool)
    {
        $this->getDiscussionTool = $getDiscussionTool;
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $discussionId = $this->discussionId($request);

        if ($discussionId <= 0) {
            return $this->notFound();
        }

        $query = $request->getQueryParams();
        $offset = max(0, $this->intValue($query, ['post_offset', 'offset'], 0));
        $limit = $this->intValue($query, ['post_limit', 'limit'], self::DEFAULT_LIMIT);
        $limit = max(1, min(self::MAX_LIMIT, $limit));

        $markdown = $this->getDiscussionTool->renderDiscussionMarkdown(
            $discussionId,
            $this->actor($request),
            $offset,
            $limit
        );

        if ($markdown === null) {
            return $this->notFound();
        }

        return CorsHeaders::apply(new TextResponse($markdown, 200, [
            'Content-Type' => 'text/markdown; charset=utf-8',
            'X-Content-Type-Options' => 'nosniff',
            // The body is rendered for the requesting actor (visibility,
            // hidden placeholders, future approval rules). It must never be
            // stored by a shared proxy under the discussion URL.
            'Cache-Control' => 'private, no-store',
        ]));
    }

    /**
     * Flarum merges route parameters into query params, but PSR-7 handlers can
     * also expose them as a `routeParameters` attribute. Accept `/637` and
     * `/637-some-slug.md` alike.
     */
    private function discussionId(ServerRequestInterface $request): int
    {
        $raw = $request->getQueryParams()['id']
            ?? $request->getAttribute('id')
            ?? null;

        if ($raw === null) {
            $routeParams = $request->getAttribute('routeParameters');

            if (is_array($routeParams)) {
                $raw = $routeParams['id'] ?? null;
            }
        }

        if (! is_scalar($raw)) {
            return 0;
        }

        $raw = (string) $raw;

        if (preg_match('/^(\d+)/', $raw, $matches) === 1) {
            return (int) $matches[1];
        }

        return 0;
    }

    /**
     * @param array<string, mixed> $query
     * @param array<int, string> $keys
     */
    private function intValue(array $query, array $keys, int $default): int
    {
        foreach ($keys as $key) {
            if (array_key_exists($key, $query) && is_numeric($query[$key])) {
                return (int) $query[$key];
            }
        }

        return $default;
    }

    private function actor(ServerRequestInterface $request): User
    {
        try {
            $actor = \Flarum\Http\RequestUtil::getActor($request);

            if ($actor instanceof User) {
                return $actor;
            }
        } catch (\Throwable) {
            // Request built without the Flarum actor middleware.
        }

        $actor = $request->getAttribute('actor');

        return $actor instanceof User ? $actor : new Guest();
    }

    private function notFound(): ResponseInterface
    {
        return CorsHeaders::apply(new TextResponse(
            "# 404 — النقاش غير موجود أو غير متاح (Discussion not found)\n",
            404,
            [
                'Content-Type' => 'text/markdown; charset=utf-8',
                'X-Content-Type-Options' => 'nosniff',
            ]
        ));
    }
}
