<?php

declare(strict_types=1);

namespace Itqan\CommunityMcp\Http\Middleware;

use Flarum\Settings\SettingsRepositoryInterface;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Itqan\CommunityMcp\Http\CorsHeaders;
use Laminas\Diactoros\Response\EmptyResponse;
use Laminas\Diactoros\Response\TextResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * CORS, kill-switch and per-IP rate limiting for the MCP endpoints.
 *
 * Only `/mcp` (with any suffix) and `/llms.txt` are touched; every other API
 * or forum request is passed through untouched. Preflight requests are answered
 * before the kill-switch and the rate limiter so browsers can always complete
 * the CORS handshake.
 */
class McpCorsAndRateLimitMiddleware implements MiddlewareInterface
{
    private const ENABLED_SETTING = 'itqan-community-mcp.enabled';

    private const RATE_LIMIT_SETTING = 'itqan-community-mcp.rate_limit_per_minute';

    private const DEFAULT_RATE_LIMIT = 60;

    /**
     * Cloudflare's published edge ranges (https://www.cloudflare.com/ips/).
     * Only used to decide whether an IP header may be trusted.
     *
     * @var array<int, string>
     */
    private const CLOUDFLARE_RANGES = [
        '173.245.48.0/20',
        '103.21.244.0/22',
        '103.22.200.0/22',
        '103.31.4.0/22',
        '141.101.64.0/18',
        '108.162.192.0/18',
        '190.93.240.0/20',
        '188.114.96.0/20',
        '197.234.240.0/22',
        '198.41.128.0/17',
        '162.158.0.0/15',
        '104.16.0.0/13',
        '104.24.0.0/14',
        '172.64.0.0/13',
        '131.0.72.0/22',
        '2400:cb00::/32',
        '2606:4700::/32',
        '2803:f800::/32',
        '2405:b500::/32',
        '2405:8100::/32',
        '2a06:98c0::/29',
        '2c0f:f248::/32',
    ];

    private CacheRepository $cache;

    private SettingsRepositoryInterface $settings;

    public function __construct(CacheRepository $cache, SettingsRepositoryInterface $settings)
    {
        $this->cache = $cache;
        $this->settings = $settings;
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if (! $this->isMcpPath($request->getUri()->getPath())) {
            return $handler->handle($request);
        }

        if (strtoupper($request->getMethod()) === 'OPTIONS') {
            return CorsHeaders::apply(new EmptyResponse(204, ['X-Content-Type-Options' => 'nosniff']));
        }

        if (! $this->isEnabled()) {
            return $this->jsonError(
                503,
                -32000,
                'MCP Server is currently disabled by administrator.'
            );
        }

        $limit = (int) $this->setting(self::RATE_LIMIT_SETTING, self::DEFAULT_RATE_LIMIT);

        if ($limit <= 0) {
            return CorsHeaders::apply($handler->handle($request));
        }

        $ip = $this->clientIp($request);
        $key = 'itqan_mcp_rl_'.md5($ip).'_'.floor(time() / 60);
        $used = (int) $this->cache->get($key, 0);

        if ($used >= $limit) {
            $response = $this->jsonError(
                429,
                -32029,
                'Rate limit exceeded. Please try again in a minute.',
                ['Retry-After' => '60']
            );

            return $response
                ->withHeader('X-RateLimit-Limit', (string) $limit)
                ->withHeader('X-RateLimit-Remaining', '0');
        }

        try {
            $this->cache->put($key, $used + 1, 120);
        } catch (\Throwable) {
            // A cache outage must not take the read-only MCP server down.
        }

        $remaining = max(0, $limit - $used - 1);

        return CorsHeaders::apply($handler->handle($request))
            ->withHeader('X-RateLimit-Limit', (string) $limit)
            ->withHeader('X-RateLimit-Remaining', (string) $remaining);
    }

    /**
     * Match `/mcp`, any `/mcp/...` suffix, and `/llms.txt` only.
     */
    public function isMcpPath(string $path): bool
    {
        return (bool) preg_match('#(^|/)(mcp(/|$)|llms\.txt$)#', $path);
    }

    private function isEnabled(): bool
    {
        return (bool) $this->setting(self::ENABLED_SETTING, true);
    }

    /**
     * @param mixed $default
     * @return mixed
     */
    private function setting(string $key, $default)
    {
        try {
            return $this->settings->get($key, $default);
        } catch (\Throwable) {
            return $default;
        }
    }

    private function clientIp(ServerRequestInterface $request): string
    {
        $server = $request->getServerParams();
        $remote = $server['REMOTE_ADDR'] ?? null;
        $remote = is_string($remote) && trim($remote) !== '' ? $this->normalizeIp($remote) : '';

        // Only a request that actually arrived from a trusted proxy may carry
        // a client address in a header. Otherwise a direct client could rotate
        // a fake `X-Forwarded-For` on every call and bypass the rate limiter.
        if ($remote !== '' && $this->isTrustedProxy($remote)) {
            // Cloudflare's header is authoritative when the request came from
            // Cloudflare (or from a local proxy in front of it). It is read
            // from the server params (what PHP populates) and from the PSR-7
            // headers, so tests and non-Apache runtimes behave the same.
            $cf = $server['HTTP_CF_CONNECTING_IP'] ?? $request->getHeaderLine('CF-Connecting-IP');

            if (is_string($cf) && $this->isIpAddress($cf)) {
                return $this->normalizeIp($cf);
            }

            $forwarded = $server['HTTP_X_FORWARDED_FOR'] ?? $request->getHeaderLine('X-Forwarded-For');

            if (is_string($forwarded) && trim($forwarded) !== '') {
                $first = trim(explode(',', $forwarded)[0]);

                if ($this->isIpAddress($first)) {
                    return $this->normalizeIp($first);
                }
            }
        }

        return $remote !== '' ? $remote : '127.0.0.1';
    }

    private function isIpAddress(string $value): bool
    {
        return filter_var(trim($value), FILTER_VALIDATE_IP) !== false;
    }

    /**
     * Canonical text form, so equivalent spellings of one address
     * (`2001:0db8::1`, `2001:db8::1`, IPv4-mapped IPv6) share one bucket.
     */
    private function normalizeIp(string $ip): string
    {
        $ip = trim($ip);
        $packed = @inet_pton($ip);

        if ($packed === false) {
            return $ip;
        }

        if (strlen($packed) === 16 && substr($packed, 0, 12) === "\0\0\0\0\0\0\0\0\0\0\xff\xff") {
            $packed = substr($packed, 12);
        }

        $canonical = @inet_ntop($packed);

        return is_string($canonical) ? $canonical : $ip;
    }

    /**
     * Loopback, private, link-local and reserved addresses are treated as
     * proxies (Docker networks, local nginx). Cloudflare's published ranges
     * are trusted too, so an origin that receives traffic directly from
     * Cloudflare still gets the real client address.
     */
    private function isTrustedProxy(string $ip): bool
    {
        if (! $this->isIpAddress($ip)) {
            return false;
        }

        $public = filter_var(
            $ip,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
        );

        if ($public === false) {
            return true;
        }

        foreach (self::CLOUDFLARE_RANGES as $range) {
            if ($this->ipInCidr($ip, $range)) {
                return true;
            }
        }

        return false;
    }

    private function ipInCidr(string $ip, string $cidr): bool
    {
        $parts = explode('/', $cidr, 2);

        if (count($parts) !== 2 || ! ctype_digit($parts[1])) {
            return false;
        }

        [$subnet, $bits] = $parts;
        $ipBinary = @inet_pton($ip);
        $subnetBinary = @inet_pton($subnet);

        if ($ipBinary === false || $subnetBinary === false || strlen($ipBinary) !== strlen($subnetBinary)) {
            return false;
        }

        $bits = (int) $bits;
        $maxBits = strlen($ipBinary) * 8;

        if ($bits < 0 || $bits > $maxBits) {
            return false;
        }

        $wholeBytes = intdiv($bits, 8);

        if ($wholeBytes > 0 && substr($ipBinary, 0, $wholeBytes) !== substr($subnetBinary, 0, $wholeBytes)) {
            return false;
        }

        $remainingBits = $bits % 8;

        if ($remainingBits === 0) {
            return true;
        }

        $mask = (0xFF << (8 - $remainingBits)) & 0xFF;

        return (ord($ipBinary[$wholeBytes]) & $mask) === (ord($subnetBinary[$wholeBytes]) & $mask);
    }

    /**
     * @param array<string, string> $headers
     */
    private function jsonError(int $status, int $code, string $message, array $headers = []): ResponseInterface
    {
        $body = json_encode([
            'jsonrpc' => '2.0',
            'id' => null,
            'error' => [
                'code' => $code,
                'message' => $message,
            ],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $headers = array_merge([
            'Content-Type' => 'application/json; charset=utf-8',
            'Cache-Control' => 'no-store',
            'X-Content-Type-Options' => 'nosniff',
        ], $headers);

        return CorsHeaders::apply(new TextResponse((string) $body, $status, $headers));
    }
}
