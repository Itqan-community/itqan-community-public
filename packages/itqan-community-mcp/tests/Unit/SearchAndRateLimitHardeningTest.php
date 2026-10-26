<?php

declare(strict_types=1);

namespace Itqan\CommunityMcp\Tests\Unit;

use Illuminate\Cache\ArrayStore;
use Illuminate\Cache\Repository as CacheRepository;
use Itqan\CommunityMcp\Http\Middleware\McpCorsAndRateLimitMiddleware;
use Itqan\CommunityMcp\Search\ForumSearchService;
use Itqan\CommunityMcp\Support\ArraySettings;
use Itqan\CommunityMcp\Tests\Support\TestCase;
use Laminas\Diactoros\Response\EmptyResponse;
use Laminas\Diactoros\ServerRequest;
use Laminas\Diactoros\Stream;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use ReflectionMethod;

/**
 * Security regression tests for the search input chain and the per-IP rate
 * limiter.
 *
 * - `LIKE` input must never be able to inject wildcards (`%`, `_`) or escape
 *   characters, and must be bounded before it reaches the Arabic `REPLACE()`
 *   chain.
 * - Forwarded-IP headers are only trusted when the request actually came from
 *   a proxy, so a direct client cannot rotate `X-Forwarded-For` to bypass the
 *   rate limit.
 */
final class SearchAndRateLimitHardeningTest extends TestCase
{
    // ------------------------------------------------------------------
    // LIKE / search input hardening
    // ------------------------------------------------------------------

    public function testLikePatternRejectsPureWildcardQueries(): void
    {
        foreach (['%%%', '%%%%%', '_____', '___%___', '\\\\', "%_\\"] as $query) {
            $this->assertNull($this->likePattern($query), 'Pure wildcard input must not become a LIKE pattern.');
        }
    }

    public function testLikePatternRemovesWildcardAndEscapeCharacters(): void
    {
        $this->assertSame('%القرآن%', $this->likePattern('القرآن'));
        // Underscore and percent are stripped instead of acting as wildcards.
        $this->assertSame('%ab%', $this->likePattern('a_b'));
        $this->assertSame('%100%', $this->likePattern('100%'));
        $this->assertSame('%ab%', $this->likePattern('a\\b'));
    }

    public function testLikePatternStaysWrappedForSqlInjectionShapedInput(): void
    {
        $pattern = $this->likePattern("x' OR 1=1 --");

        $this->assertSame("%x' OR 1=1 --%", $pattern);
        $this->assertStringStartsWith('%', (string) $pattern);
        $this->assertStringEndsWith('%', (string) $pattern);
    }

    public function testLikePatternIsBoundedToTheMaximumQueryLength(): void
    {
        $pattern = $this->likePattern(str_repeat('ا', 5000));

        $this->assertNotNull($pattern);
        $this->assertLessThanOrEqual(
            ForumSearchService::MAX_QUERY_LENGTH + 2,
            mb_strlen((string) $pattern, 'UTF-8')
        );
    }

    public function testLikePatternsDropsEveryWildcardVariant(): void
    {
        $patterns = $this->likePatterns(['%%%%%', '_____', '\\\\\\', '']);

        $this->assertSame([], $patterns);
    }

    public function testNormalizeQueryTruncatesOversizedInput(): void
    {
        $normalized = $this->normalizeQuery(str_repeat('ا', 5000));

        $this->assertSame(ForumSearchService::MAX_QUERY_LENGTH, mb_strlen($normalized, 'UTF-8'));
        $this->assertSame('', $this->normalizeQuery("  \n\t "));
    }

    public function testArabicReplaceExpressionUsesNoInterpolatedInput(): void
    {
        $sql = $this->normalizedArabicSql('posts.content');

        $this->assertStringContainsString('posts.content', $sql);
        $this->assertStringNotContainsString('%', $sql);
        $this->assertStringNotContainsString('?', $sql);
        $this->assertStringNotContainsString('$', $sql);
    }

    // ------------------------------------------------------------------
    // flarum/approval defence in depth
    // ------------------------------------------------------------------

    public function testUnapprovedPrivateAndHiddenPostsAreNeverReadable(): void
    {
        $this->assertFalse($this->postIsReadable($this->post(['hidden_at' => '2026-10-26 00:00:00'])));
        $this->assertFalse($this->postIsReadable($this->post(['is_private' => 1])));
        $this->assertTrue($this->postIsReadable($this->post([])));
    }

    // ------------------------------------------------------------------
    // Rate limiter IP trust
    // ------------------------------------------------------------------

    public function testSpoofedForwardedForFromUntrustedClientCannotRotateBuckets(): void
    {
        $middleware = $this->middleware(limit: 1);
        $handler = $this->terminal();

        $first = $middleware->process(
            $this->request('203.0.113.50', ['X-Forwarded-For' => '1.1.1.1']),
            $handler
        );
        $second = $middleware->process(
            $this->request('203.0.113.50', ['X-Forwarded-For' => '2.2.2.2']),
            $handler
        );

        $this->assertSame(200, $first->getStatusCode());
        $this->assertSame(429, $second->getStatusCode(), 'A public REMOTE_ADDR must own the bucket; its XFF header is ignored.');
    }

    public function testSpoofedCloudflareHeaderFromUntrustedClientIsIgnored(): void
    {
        $middleware = $this->middleware(limit: 1);
        $handler = $this->terminal();

        $first = $middleware->process(
            $this->request('198.51.100.7', ['CF-Connecting-IP' => '1.1.1.1']),
            $handler
        );
        $second = $middleware->process(
            $this->request('198.51.100.7', ['CF-Connecting-IP' => '2.2.2.2']),
            $handler
        );

        $this->assertSame(200, $first->getStatusCode());
        $this->assertSame(429, $second->getStatusCode());
    }

    public function testForwardedForIsHonouredBehindALoopbackProxy(): void
    {
        $middleware = $this->middleware(limit: 1);
        $handler = $this->terminal();

        $first = $middleware->process(
            $this->request('127.0.0.1', ['X-Forwarded-For' => '203.0.113.10, 127.0.0.1']),
            $handler
        );
        $second = $middleware->process(
            $this->request('127.0.0.1', ['X-Forwarded-For' => '203.0.113.11, 127.0.0.1']),
            $handler
        );

        $this->assertSame(200, $first->getStatusCode());
        $this->assertSame(200, $second->getStatusCode(), 'Distinct real clients behind a trusted proxy get distinct buckets.');
    }

    public function testCloudflareConnectingIpIsHonouredFromCloudflareEdge(): void
    {
        $middleware = $this->middleware(limit: 1);
        $handler = $this->terminal();

        $first = $middleware->process(
            $this->request('173.245.48.10', ['CF-Connecting-IP' => '203.0.113.20']),
            $handler
        );
        $second = $middleware->process(
            $this->request('173.245.48.10', ['CF-Connecting-IP' => '203.0.113.21']),
            $handler
        );

        $this->assertSame(200, $first->getStatusCode());
        $this->assertSame(200, $second->getStatusCode());
    }

    public function testEquivalentIpv6SpellingsShareOneBucket(): void
    {
        $middleware = $this->middleware(limit: 1);
        $handler = $this->terminal();

        $first = $middleware->process(
            $this->request('127.0.0.1', ['X-Forwarded-For' => '2001:db8::1']),
            $handler
        );
        $second = $middleware->process(
            $this->request('127.0.0.1', ['X-Forwarded-For' => '2001:0db8:0000:0000:0000:0000:0000:0001']),
            $handler
        );

        $this->assertSame(200, $first->getStatusCode());
        $this->assertSame(429, $second->getStatusCode());
    }

    public function testInvalidForwardedHeaderFallsBackToRemoteAddress(): void
    {
        $middleware = $this->middleware(limit: 1);
        $handler = $this->terminal();

        $first = $middleware->process(
            $this->request('127.0.0.1', ['X-Forwarded-For' => 'not-an-ip']),
            $handler
        );
        $second = $middleware->process(
            $this->request('127.0.0.1', ['X-Forwarded-For' => 'also-not-an-ip']),
            $handler
        );

        $this->assertSame(200, $first->getStatusCode());
        $this->assertSame(429, $second->getStatusCode());
    }

    public function testRateLimitErrorResponseCannotBeSniffedAsHtml(): void
    {
        $middleware = $this->middleware(limit: 1);
        $handler = $this->terminal();

        $middleware->process($this->request('203.0.113.60'), $handler);
        $response = $middleware->process($this->request('203.0.113.60'), $handler);

        $this->assertSame(429, $response->getStatusCode());
        $this->assertSame('nosniff', $response->getHeaderLine('X-Content-Type-Options'));
        $this->assertStringContainsString('application/json', $response->getHeaderLine('Content-Type'));
    }

    public function testDisabledKillSwitchResponseCannotBeSniffedAsHtml(): void
    {
        $middleware = new McpCorsAndRateLimitMiddleware(
            new CacheRepository(new ArrayStore()),
            new ArraySettings(['itqan-community-mcp.enabled' => '0'])
        );

        $response = $middleware->process($this->request('203.0.113.61'), $this->terminal());

        $this->assertSame(503, $response->getStatusCode());
        $this->assertSame('nosniff', $response->getHeaderLine('X-Content-Type-Options'));
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    private function service(): ForumSearchService
    {
        return new ForumSearchService(
            new CacheRepository(new ArrayStore()),
            new ArraySettings()
        );
    }

    private function likePattern(string $term): ?string
    {
        $method = new ReflectionMethod(ForumSearchService::class, 'likePattern');
        $method->setAccessible(true);

        return $method->invoke($this->service(), $term);
    }

    /**
     * @param array<int, string> $terms
     * @return array<int, string>
     */
    private function likePatterns(array $terms): array
    {
        $method = new ReflectionMethod(ForumSearchService::class, 'likePatterns');
        $method->setAccessible(true);

        return $method->invoke($this->service(), $terms);
    }

    private function normalizeQuery(string $query): string
    {
        $method = new ReflectionMethod(ForumSearchService::class, 'normalizeQuery');
        $method->setAccessible(true);

        return (string) $method->invoke($this->service(), $query);
    }

    private function normalizedArabicSql(string $column): string
    {
        $method = new ReflectionMethod(ForumSearchService::class, 'normalizedArabicSql');
        $method->setAccessible(true);

        return (string) $method->invoke(null, $column);
    }

    private function approvalService(bool $hasApprovalColumn): ForumSearchService
    {
        return new class($hasApprovalColumn) extends ForumSearchService {
            private bool $hasApprovalColumn;

            public function __construct(bool $hasApprovalColumn)
            {
                parent::__construct(new CacheRepository(new ArrayStore()), new ArraySettings());

                $this->hasApprovalColumn = $hasApprovalColumn;
            }

            public function hasColumn(string $table, string $column): bool
            {
                return $this->hasApprovalColumn && $table === 'posts' && $column === 'is_approved';
            }
        };
    }

    private function postIsReadable(\Flarum\Post\Post $post): bool
    {
        $method = new ReflectionMethod(ForumSearchService::class, 'postIsReadableBy');
        $method->setAccessible(true);

        return (bool) $method->invoke($this->approvalService(false), $post, new \Flarum\User\Guest());
    }

    /**
     * @param array<string, mixed> $attributes
     */
    private function post(array $attributes): \Flarum\Post\Post
    {
        $post = new \Flarum\Post\Post();
        $post->setRawAttributes(array_merge([
            'id' => 1,
            'content' => '<t>x</t>',
            'hidden_at' => null,
            'is_private' => 0,
        ], $attributes), true);

        return $post;
    }

    private function middleware(int $limit): McpCorsAndRateLimitMiddleware
    {
        return new McpCorsAndRateLimitMiddleware(
            new CacheRepository(new ArrayStore()),
            new ArraySettings([
                'itqan-community-mcp.enabled' => '1',
                'itqan-community-mcp.rate_limit_per_minute' => (string) $limit,
            ])
        );
    }

    /**
     * @param array<string, string> $headers
     */
    private function request(string $remoteAddr, array $headers = []): ServerRequestInterface
    {
        $stream = new Stream('php://temp', 'wb+');
        $stream->write('{"jsonrpc":"2.0","id":1,"method":"ping"}');
        $stream->rewind();

        return new ServerRequest(
            ['REMOTE_ADDR' => $remoteAddr],
            [],
            'http://localhost:8080/api/mcp',
            'POST',
            $stream,
            $headers,
            [],
            []
        );
    }

    private function terminal(): RequestHandlerInterface
    {
        return new class implements RequestHandlerInterface {
            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                return new EmptyResponse(200);
            }
        };
    }
}
