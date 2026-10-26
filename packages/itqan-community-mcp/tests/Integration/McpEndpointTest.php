<?php

declare(strict_types=1);

namespace Itqan\CommunityMcp\Tests\Integration;

use Illuminate\Cache\ArrayStore;
use Illuminate\Cache\Repository as CacheRepository;
use Itqan\CommunityMcp\Formatter\PostMarkdownFormatter;
use Itqan\CommunityMcp\Http\Controller\LlmsTxtController;
use Itqan\CommunityMcp\Http\Controller\McpStreamableController;
use Itqan\CommunityMcp\Http\Middleware\McpCorsAndRateLimitMiddleware;
use Itqan\CommunityMcp\Mcp\JsonRpcDispatcher;
use Itqan\CommunityMcp\Mcp\Resources\ForumTaxonomyResource;
use Itqan\CommunityMcp\Mcp\Tools\GetDiscussionTool;
use Itqan\CommunityMcp\Mcp\Tools\ListTagsTool;
use Itqan\CommunityMcp\Search\ForumSearchService;
use Itqan\CommunityMcp\Support\ArraySettings;
use Itqan\CommunityMcp\Support\Entity;
use Itqan\CommunityMcp\Tests\Support\FlarumApp;
use Itqan\CommunityMcp\Tests\Support\HttpRequest;
use Itqan\CommunityMcp\Tests\Support\TestCase;
use Laminas\Diactoros\Response\EmptyResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * End-to-end tests through the booted Flarum HTTP stack.
 *
 * These need the container network (the database host is `db`), so the suite
 * skips itself with an explicit reason when run on the host. Inside the
 * container:
 *
 *     docker exec community-web-1 php packages/itqan-community-mcp/tests/run.php --suite=integration
 */
final class McpEndpointTest extends TestCase
{
    private object $app;

    /** @var \Illuminate\Contracts\Container\Container */
    private $container;

    /** @var \Flarum\Http\Server */
    private $handler;

    public function setUp(): void
    {
        $app = FlarumApp::boot();

        if ($app === null) {
            $this->markTestSkipped('Flarum app unavailable: '.FlarumApp::error());
        }

        $this->app = $app;
        $this->container = $app->getContainer();
        $this->handler = $app->getRequestHandler();
    }

    // ------------------------------------------------------------------
    // McpStreamableController
    // ------------------------------------------------------------------

    public function testPostInitializeReturnsJsonRpcResult(): void
    {
        $response = $this->handle(HttpRequest::json('POST', '/api/mcp', [
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => 'initialize',
            'params' => ['protocolVersion' => '2025-06-18'],
        ]));

        $body = (string) $response->getBody();
        $decoded = json_decode($body, true);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringContainsString('application/json', $response->getHeaderLine('Content-Type'));
        $this->assertSame('*', $response->getHeaderLine('Access-Control-Allow-Origin'));
        $this->assertSame('2025-06-18', $response->getHeaderLine('MCP-Protocol-Version'));
        $this->assertSame('no-store', $response->getHeaderLine('Cache-Control'));
        $this->assertSame('nosniff', $response->getHeaderLine('X-Content-Type-Options'));
        $this->assertSame('itqan-community-mcp', $decoded['result']['serverInfo']['name']);
        $this->assertSame('2025-06-18', $decoded['result']['protocolVersion']);
        $this->assertStringNotContainsString('\u', $body, 'Arabic JSON must stay unescaped.');
        $this->assertStringContainsString('إتقان', $body);
    }

    public function testPostToolsListReturnsFiveTools(): void
    {
        $response = $this->handle(HttpRequest::json('POST', '/api/mcp', [
            'jsonrpc' => '2.0',
            'id' => 2,
            'method' => 'tools/list',
        ]));

        $decoded = json_decode((string) $response->getBody(), true);
        $names = array_column($decoded['result']['tools'], 'name');

        $this->assertSame(200, $response->getStatusCode());
        $this->assertCount(5, $names);
        $this->assertSame([
            'list_tags',
            'search_discussions',
            'get_discussion',
            'search_posts',
            'get_user_contributions',
        ], $names);
        $this->assertNotEmpty($response->getHeaderLine('X-RateLimit-Limit'));
    }

    public function testPostPingReturnsEmptyResultObject(): void
    {
        $response = $this->handle(HttpRequest::json('POST', '/api/mcp', [
            'jsonrpc' => '2.0',
            'id' => 3,
            'method' => 'ping',
        ]));
        $body = (string) $response->getBody();
        $decoded = json_decode($body, true);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('*', $response->getHeaderLine('Access-Control-Allow-Origin'));
        $this->assertStringContainsString('"result":{}', $body);
        $this->assertSame([], $decoded['result']);
    }

    public function testPostNotificationReturns202WithoutBody(): void
    {
        $response = $this->handle(HttpRequest::json('POST', '/api/mcp', [
            'jsonrpc' => '2.0',
            'method' => 'notifications/initialized',
        ]));

        $this->assertSame(202, $response->getStatusCode());
        $this->assertSame('', (string) $response->getBody());
        $this->assertSame('*', $response->getHeaderLine('Access-Control-Allow-Origin'));
    }

    public function testPostParseErrorReturnsJsonRpcError(): void
    {
        $response = $this->handle(HttpRequest::create(
            'POST',
            '/api/mcp',
            '{broken',
            ['Content-Type' => 'application/json']
        ));

        $decoded = json_decode((string) $response->getBody(), true);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame(-32700, $decoded['error']['code']);
    }

    public function testGetDiscoveryDocument(): void
    {
        $response = $this->handle(HttpRequest::create('GET', '/api/mcp', '', ['Accept' => 'application/json']));
        $body = (string) $response->getBody();
        $decoded = json_decode($body, true);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringContainsString('application/json', $response->getHeaderLine('Content-Type'));
        $this->assertSame('itqan-community-mcp', $decoded['name']);
        $this->assertSame('1.0.0', $decoded['version']);
        $this->assertStringEndsWith('/api/mcp', $decoded['endpoint']);
        $this->assertCount(5, $decoded['tools']);
        $this->assertSame(['2025-06-18', '2025-03-26', '2024-11-05'], $decoded['protocolVersions']);
        $this->assertStringContainsString("\n    \"name\"", $body, 'Discovery document should be pretty printed.');
    }

    public function testGetWithEventStreamOnlyReturns405(): void
    {
        $response = $this->handle(HttpRequest::create('GET', '/api/mcp', '', ['Accept' => 'text/event-stream']));
        $decoded = json_decode((string) $response->getBody(), true);

        $this->assertSame(405, $response->getStatusCode());
        $this->assertSame('POST, GET, OPTIONS', $response->getHeaderLine('Allow'));
        $this->assertSame(-32000, $decoded['error']['code']);
    }

    public function testGetWithSseAndJsonReturnsDiscovery(): void
    {
        $response = $this->handle(HttpRequest::create(
            'GET',
            '/api/mcp',
            '',
            ['Accept' => 'text/event-stream, application/json']
        ));

        $this->assertSame(200, $response->getStatusCode());
    }

    public function testOptionsPreflight(): void
    {
        $response = $this->handle(HttpRequest::create('OPTIONS', '/api/mcp'));

        $this->assertSame(204, $response->getStatusCode());
        $this->assertSame('*', $response->getHeaderLine('Access-Control-Allow-Origin'));
        $this->assertSame('GET, POST, OPTIONS', $response->getHeaderLine('Access-Control-Allow-Methods'));
        $this->assertSame('86400', $response->getHeaderLine('Access-Control-Max-Age'));
        $this->assertStringContainsString('Mcp-Session-Id', $response->getHeaderLine('Access-Control-Allow-Headers'));
    }

    public function testControllerRejectsUnsupportedMethod(): void
    {
        /** @var McpStreamableController $controller */
        $controller = $this->container->make(McpStreamableController::class);
        $response = $controller->handle(HttpRequest::create('DELETE', '/api/mcp'));

        $this->assertSame(405, $response->getStatusCode());
        $this->assertSame('POST, GET, OPTIONS', $response->getHeaderLine('Allow'));
    }

    // ------------------------------------------------------------------
    // CORS, kill-switch and rate limiting
    // ------------------------------------------------------------------

    public function testMcpRoutesCarryCorsHeaders(): void
    {
        foreach ([
            HttpRequest::create('GET', '/api/mcp', '', ['Accept' => 'application/json']),
            HttpRequest::create('POST', '/api/mcp', '{"jsonrpc":"2.0","id":9,"method":"ping"}', ['Content-Type' => 'application/json']),
        ] as $request) {
            $response = $this->handle($request);

            $this->assertSame('*', $response->getHeaderLine('Access-Control-Allow-Origin'));
            $this->assertStringContainsString('X-RateLimit-Remaining', $response->getHeaderLine('Access-Control-Expose-Headers'));
            $this->assertNotSame('', $response->getHeaderLine('X-RateLimit-Limit'));
        }
    }

    public function testNonMcpRoutesAreNotModified(): void
    {
        $response = $this->handle(HttpRequest::create('GET', '/api/discussions'));

        $this->assertLessThan(500, $response->getStatusCode());
        $this->assertSame('', $response->getHeaderLine('Access-Control-Allow-Origin'));
        $this->assertSame('', $response->getHeaderLine('X-RateLimit-Limit'));
    }

    public function testMiddlewareReturns503WhenDisabled(): void
    {
        $middleware = new McpCorsAndRateLimitMiddleware(
            new CacheRepository(new ArrayStore()),
            new ArraySettings(['itqan-community-mcp.enabled' => '0'])
        );

        $response = $middleware->process(HttpRequest::create('POST', '/api/mcp'), $this->terminal(200));
        $decoded = json_decode((string) $response->getBody(), true);

        $this->assertSame(503, $response->getStatusCode());
        $this->assertSame(-32000, $decoded['error']['code']);
        $this->assertSame('*', $response->getHeaderLine('Access-Control-Allow-Origin'));
    }

    public function testMiddlewareReturns429WhenRateLimitExceeded(): void
    {
        $middleware = new McpCorsAndRateLimitMiddleware(
            new CacheRepository(new ArrayStore()),
            new ArraySettings([
                'itqan-community-mcp.enabled' => '1',
                'itqan-community-mcp.rate_limit_per_minute' => '2',
            ])
        );

        $handler = $this->terminal(200);
        $request = HttpRequest::create('POST', '/api/mcp');

        $first = $middleware->process($request, $handler);
        $second = $middleware->process($request, $handler);
        $third = $middleware->process($request, $handler);
        $decoded = json_decode((string) $third->getBody(), true);

        $this->assertSame(200, $first->getStatusCode());
        $this->assertSame('1', $first->getHeaderLine('X-RateLimit-Remaining'));
        $this->assertSame(200, $second->getStatusCode());
        $this->assertSame('0', $second->getHeaderLine('X-RateLimit-Remaining'));
        $this->assertSame(429, $third->getStatusCode());
        $this->assertSame(-32029, $decoded['error']['code']);
        $this->assertSame('60', $third->getHeaderLine('Retry-After'));
        $this->assertSame('0', $third->getHeaderLine('X-RateLimit-Remaining'));
    }

    // ------------------------------------------------------------------
    // SKILL.md, discussion Markdown and llms.txt
    // ------------------------------------------------------------------

    public function testSkillMdEndpoint(): void
    {
        $response = $this->handle(HttpRequest::create('GET', '/api/mcp/SKILL.md'));
        $body = (string) $response->getBody();

        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringContainsString('text/markdown', $response->getHeaderLine('Content-Type'));
        $this->assertStringContainsString('Itqan Community MCP', $body);
        $this->assertStringContainsString('search_discussions', $body);
        $this->assertSame('nosniff', $response->getHeaderLine('X-Content-Type-Options'));
    }

    public function testDiscussionMarkdownEndpoint(): void
    {
        $discussionId = FlarumApp::discussionId();

        if ($discussionId <= 0) {
            $this->markTestSkipped('No visible discussion in the local database.');
        }

        $response = $this->handle(HttpRequest::create(
            'GET',
            '/api/mcp/discussions/'.$discussionId.'.md?post_limit=2'
        ));
        $body = (string) $response->getBody();

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('text/markdown; charset=utf-8', $response->getHeaderLine('Content-Type'));
        $this->assertStringContainsString('#', $body);
        $this->assertStringContainsString('معرف المناقشة', $body);
        $this->assertStringContainsString('الردود', $body);
        $this->assertSame('nosniff', $response->getHeaderLine('X-Content-Type-Options'));
        $this->assertSame('*', $response->getHeaderLine('Access-Control-Allow-Origin'));

        // The Markdown is actor-scoped, so a shared cache must never store it.
        $cacheControl = strtolower($response->getHeaderLine('Cache-Control'));
        $this->assertStringContainsString('private', $cacheControl);
        $this->assertStringContainsString('no-store', $cacheControl);
    }

    public function testDiscussionMarkdownAcceptsSlugSuffix(): void
    {
        $discussionId = FlarumApp::discussionId();

        if ($discussionId <= 0) {
            $this->markTestSkipped('No visible discussion in the local database.');
        }

        $response = $this->handle(HttpRequest::create(
            'GET',
            '/api/mcp/discussions/'.$discussionId.'-some-slug.md?post_limit=1'
        ));

        $this->assertSame(200, $response->getStatusCode());
    }

    public function testDiscussionMarkdownReturns404ForMissingDiscussion(): void
    {
        $response = $this->handle(HttpRequest::create('GET', '/api/mcp/discussions/99999999.md'));
        $body = (string) $response->getBody();

        $this->assertSame(404, $response->getStatusCode());
        $this->assertStringContainsString('404', $body);
        $this->assertStringContainsString('text/markdown', $response->getHeaderLine('Content-Type'));
    }

    public function testLlmsTxtEndpoint(): void
    {
        $response = $this->handle(HttpRequest::create('GET', '/llms.txt'));
        $body = (string) $response->getBody();

        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringContainsString('text/plain', $response->getHeaderLine('Content-Type'));
        $this->assertStringContainsString('AI & MCP Integration', $body);
        $this->assertStringContainsString('/api/mcp/discussions/{id}.md', $body);
        $this->assertStringContainsString('/api/mcp/SKILL.md', $body);
        $this->assertStringContainsString('الأقسام والوسوم الرئيسية', $body);
        $this->assertStringContainsString('Featured & Recent Discussions', $body);
        $this->assertStringContainsString('nosniff', $response->getHeaderLine('X-Content-Type-Options'));
    }

    // ------------------------------------------------------------------
    // Security hardening
    // ------------------------------------------------------------------

    public function testBatchAmplificationIsRejectedOverHttp(): void
    {
        // 50 tool calls in one HTTP request would only consume one rate-limit
        // slot; the dispatcher must reject the batch outright.
        $batch = array_fill(0, JsonRpcDispatcher::MAX_BATCH_SIZE + 40, [
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => 'tools/call',
            'params' => ['name' => 'list_tags', 'arguments' => []],
        ]);

        $response = $this->handle(HttpRequest::json('POST', '/api/mcp', $batch));
        $decoded = json_decode((string) $response->getBody(), true);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame(-32600, $decoded['error']['code']);
        $this->assertStringContainsString(
            'Batch size exceeds maximum of '.JsonRpcDispatcher::MAX_BATCH_SIZE,
            $decoded['error']['message']
        );
        $this->assertSame('nosniff', $response->getHeaderLine('X-Content-Type-Options'));
    }

    public function testOversizedPayloadIsRejectedOverHttp(): void
    {
        $response = $this->handle(HttpRequest::create(
            'POST',
            '/api/mcp',
            str_repeat('a', JsonRpcDispatcher::MAX_PAYLOAD_BYTES + 1),
            ['Content-Type' => 'application/json']
        ));
        $decoded = json_decode((string) $response->getBody(), true);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame(-32600, $decoded['error']['code']);
        $this->assertStringContainsString('Payload exceeds', $decoded['error']['message']);
    }

    public function testOversizedContentLengthIsRejectedBeforeTheBodyIsRead(): void
    {
        $response = $this->handle(HttpRequest::create(
            'POST',
            '/api/mcp',
            '{"jsonrpc":"2.0","id":1,"method":"ping"}',
            [
                'Content-Type' => 'application/json',
                'Content-Length' => (string) (JsonRpcDispatcher::MAX_PAYLOAD_BYTES + 1),
            ]
        ));
        $decoded = json_decode((string) $response->getBody(), true);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame(-32600, $decoded['error']['code']);
        $this->assertStringContainsString('Payload exceeds', $decoded['error']['message']);
    }

    public function testWildcardOnlyAndOversizedSearchInputAreHarmless(): void
    {
        $service = $this->searchService();
        $actor = new \Flarum\User\Guest();

        foreach (['%%%%%', '_____', '\\', "%_\\"] as $wildcards) {
            $discussions = $service->searchDiscussions($actor, $wildcards, null, null, 'relevance', 5, 0);
            $this->assertSame([], $discussions['results'], 'Wildcard-only query must not match everything: '.$wildcards);

            $posts = $service->searchPosts($actor, $wildcards, null, null, 5);
            $this->assertSame([], $posts['results'], 'Wildcard-only query must not match everything: '.$wildcards);
        }

        $huge = str_repeat('a', 5000);
        $result = $service->searchPosts($actor, $huge, null, null, 3);

        $this->assertSame(ForumSearchService::MAX_QUERY_LENGTH, mb_strlen((string) $result['query'], 'UTF-8'));
        $this->assertLessThanOrEqual(ForumSearchService::MAX_QUERY_LENGTH, mb_strlen((string) $result['query'], 'UTF-8'));
    }

    public function testRestrictedTagsNeverLeakToGuestsOrThePublicCache(): void
    {
        $db = $this->db();
        $slug = 'mcp-secret-tag-'.bin2hex(random_bytes(4));

        $db->beginTransaction();

        try {
            $db->table('tags')->insert([
                'name' => 'Secret Staff Tag',
                'slug' => $slug,
                'description' => 'internal only',
                'position' => 999,
                'parent_id' => null,
                'default_sort' => null,
                'is_restricted' => 1,
                'is_hidden' => 0,
                'discussion_count' => 0,
                'localised_last_discussion' => '{}',
            ]);

            $service = $this->searchService();
            $tool = new ListTagsTool($service);
            $guestOutput = $tool->execute([], new \Flarum\User\Guest());

            $this->assertStringNotContainsString($slug, $guestOutput);
            $this->assertStringNotContainsString('Secret Staff Tag', $guestOutput);
            $this->assertStringContainsString('general', $guestOutput);

            // The actor-less cached path is what `/llms.txt` and any other
            // public caller uses; it must be built as a guest.
            $hierarchy = $service->getTagsHierarchy(true);
            $this->assertNotContains($slug, array_column($hierarchy['all'], 'slug'));

            $resource = new ForumTaxonomyResource($service, $tool);
            $this->assertStringNotContainsString($slug, $resource->render(new \Flarum\User\Guest()));

            $llms = new LlmsTxtController($service, new CacheRepository(new ArrayStore()));
            $this->assertStringNotContainsString($slug, $llms->content());
        } finally {
            $db->rollBack();
        }
    }

    public function testApprovalGuardIsAppliedWhenTheColumnExists(): void
    {
        $db = $this->db();

        $service = new class($db) extends ForumSearchService {
            public function __construct($db)
            {
                parent::__construct(new CacheRepository(new ArrayStore()), new ArraySettings(), $db);
            }

            public function hasColumn(string $table, string $column): bool
            {
                return $table === 'posts' && $column === 'is_approved';
            }
        };

        $approver = new class extends \Flarum\User\User {
            public function hasPermission($permission)
            {
                return $permission === 'discussion.approvePosts';
            }

            public function isAdmin()
            {
                return false;
            }
        };

        $method = new \ReflectionMethod(ForumSearchService::class, 'applyApprovedConstraint');
        $method->setAccessible(true);

        $query = \Flarum\Post\CommentPost::query();
        $method->invoke($service, $query, new \Flarum\User\Guest(), 'posts');
        $this->assertStringContainsString('is_approved', $query->toSql());

        $query = \Flarum\Post\CommentPost::query();
        $method->invoke($service, $query, $approver, 'posts');
        $this->assertStringNotContainsString('is_approved', $query->toSql());

        // A real install without flarum/approval has no column: no constraint.
        $query = \Flarum\Post\CommentPost::query();
        $method->invoke($this->searchService(), $query, new \Flarum\User\Guest(), 'posts');
        $this->assertStringNotContainsString('is_approved', $query->toSql());
    }

    public function testHiddenPlaceholderPostIsMaskedInMarkdownEndpoint(): void
    {
        $db = $this->db();
        $suffix = bin2hex(random_bytes(4));
        $now = date('Y-m-d H:i:s');

        $db->beginTransaction();

        try {
            $discussionId = (int) $db->table('discussions')->insertGetId([
                'title' => 'MCP hidden placeholder fixture '.$suffix,
                'comment_count' => 3,
                'participant_count' => 1,
                'post_number_index' => 3,
                'created_at' => $now,
                'user_id' => 4,
                'slug' => 'mcp-hidden-placeholder-'.$suffix,
                'is_private' => 0,
                'votes' => 0,
                'hotness' => 0,
                'view_count' => 0,
            ]);

            $firstPostId = (int) $db->table('posts')->insertGetId([
                'discussion_id' => $discussionId,
                'number' => 1,
                'created_at' => $now,
                'user_id' => 4,
                'type' => 'comment',
                'content' => '<t>OPENING_POST_'.$suffix.'</t>',
                'is_private' => 0,
                'reply_count' => 0,
                'depth' => 0,
            ]);

            $hiddenPostId = (int) $db->table('posts')->insertGetId([
                'discussion_id' => $discussionId,
                'number' => 2,
                'created_at' => $now,
                'user_id' => 1,
                'type' => 'comment',
                'content' => '<t>TOP_SECRET_HIDDEN_BODY_'.$suffix.'</t>',
                'hidden_at' => $now,
                'is_private' => 0,
                'reply_count' => 1,
                'depth' => 0,
            ]);

            $childPostId = (int) $db->table('posts')->insertGetId([
                'discussion_id' => $discussionId,
                'number' => 3,
                'created_at' => $now,
                'user_id' => 4,
                'type' => 'comment',
                'content' => '<t>VISIBLE_CHILD_BODY_'.$suffix.'</t>',
                'is_private' => 0,
                'parent_id' => $hiddenPostId,
                'reply_count' => 0,
                'depth' => 1,
            ]);

            $db->table('discussions')->where('id', $discussionId)->update([
                'first_post_id' => $firstPostId,
                'last_post_id' => $childPostId,
                'last_post_number' => 3,
                'last_posted_at' => $now,
                'last_posted_user_id' => 4,
            ]);

            $tool = new GetDiscussionTool($this->searchService());
            $markdown = (string) $tool->renderDiscussionMarkdown($discussionId, new \Flarum\User\Guest(), 0, 50);

            $this->assertStringContainsString('OPENING_POST_'.$suffix, $markdown);
            $this->assertStringContainsString('VISIBLE_CHILD_BODY_'.$suffix, $markdown);
            $this->assertStringContainsString(PostMarkdownFormatter::HIDDEN_MESSAGE, $markdown);
            $this->assertStringContainsString(Entity::DELETED_LABEL, $markdown);
            $this->assertStringNotContainsString('TOP_SECRET_HIDDEN_BODY_'.$suffix, $markdown);
            $this->assertStringNotContainsString('@admin', $markdown);

            // Same guarantee through the public Markdown endpoint.
            $response = $this->handle(HttpRequest::create(
                'GET',
                '/api/mcp/discussions/'.$discussionId.'.md?post_limit=50'
            ));
            $body = (string) $response->getBody();

            $this->assertSame(200, $response->getStatusCode());
            $this->assertStringNotContainsString('TOP_SECRET_HIDDEN_BODY_'.$suffix, $body);
            $this->assertStringNotContainsString('@admin', $body);
            $this->assertStringContainsString(PostMarkdownFormatter::HIDDEN_MESSAGE, $body);
        } finally {
            $db->rollBack();
        }
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    /**
     * @return \Illuminate\Database\ConnectionInterface
     */
    private function db()
    {
        $manager = $this->container->make('db');

        return method_exists($manager, 'connection') ? $manager->connection() : $manager;
    }

    private function searchService(): ForumSearchService
    {
        return new ForumSearchService(
            new CacheRepository(new ArrayStore()),
            new ArraySettings(),
            $this->db()
        );
    }

    private function handle(ServerRequestInterface $request): ResponseInterface
    {
        return $this->handler->handle($request);
    }

    private function terminal(int $status): RequestHandlerInterface
    {
        return new class($status) implements RequestHandlerInterface {
            private int $status;

            public function __construct(int $status)
            {
                $this->status = $status;
            }

            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                return new EmptyResponse($this->status);
            }
        };
    }
}
