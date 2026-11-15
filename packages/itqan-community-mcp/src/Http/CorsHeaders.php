<?php

declare(strict_types=1);

namespace Itqan\CommunityMcp\Http;

use Psr\Http\Message\ResponseInterface;

/**
 * CORS headers shared by the MCP middleware and controllers.
 *
 * MCP clients connect from desktop apps, browser-based tools and remote
 * containers, so preflight requests and actual responses must carry the same
 * policy. `Mcp-Session-Id` is exposed even though this server is stateless, so
 * clients that look for it do not treat its absence as a protocol failure.
 */
final class CorsHeaders
{
    public const ALLOW_ORIGIN = '*';

    public const ALLOW_METHODS = 'GET, POST, OPTIONS';

    public const ALLOW_HEADERS = 'Content-Type, Authorization, Accept, Mcp-Session-Id, MCP-Protocol-Version';

    public const EXPOSE_HEADERS = 'Mcp-Session-Id, MCP-Protocol-Version, X-RateLimit-Limit, X-RateLimit-Remaining';

    public const MAX_AGE = '86400';

    /**
     * @return array<string, string>
     */
    public static function all(): array
    {
        return [
            'Access-Control-Allow-Origin' => self::ALLOW_ORIGIN,
            'Access-Control-Allow-Methods' => self::ALLOW_METHODS,
            'Access-Control-Allow-Headers' => self::ALLOW_HEADERS,
            'Access-Control-Expose-Headers' => self::EXPOSE_HEADERS,
            'Access-Control-Max-Age' => self::MAX_AGE,
        ];
    }

    public static function apply(ResponseInterface $response): ResponseInterface
    {
        foreach (self::all() as $name => $value) {
            $response = $response->withHeader($name, $value);
        }

        return $response;
    }
}
