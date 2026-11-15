<?php

declare(strict_types=1);

namespace Itqan\CommunityMcp\Tests\Support;

use Laminas\Diactoros\ServerRequest;
use Laminas\Diactoros\Stream;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Builds PSR-7 requests for the controller and full-stack integration tests.
 *
 * Each request gets a distinct REMOTE_ADDR so the middleware's per-IP rate
 * limit is not exhausted by the test suite itself.
 */
final class HttpRequest
{
    private static int $counter = 0;

    /**
     * @param array<string, string> $headers
     * @param array<string, mixed> $query
     */
    public static function create(
        string $method,
        string $path,
        string $body = '',
        array $headers = [],
        array $query = []
    ): ServerRequestInterface {
        $stream = new Stream('php://temp', 'wb+');
        $stream->write($body);
        $stream->rewind();

        $uri = 'http://localhost:8080'.$path;

        if ($query === []) {
            $queryString = parse_url($uri, PHP_URL_QUERY);

            if (is_string($queryString)) {
                parse_str($queryString, $query);
            }
        }

        self::$counter++;
        $ip = '198.18.'.intdiv(self::$counter, 250).'.'.((self::$counter % 250) + 1);

        return new ServerRequest(
            ['REMOTE_ADDR' => $ip, 'HTTP_USER_AGENT' => 'itqan-community-mcp-tests'],
            [],
            $uri,
            $method,
            $stream,
            $headers,
            [],
            $query
        );
    }

    /**
     * @param array<string, mixed> $payload
     */
    public static function json(string $method, string $path, array $payload): ServerRequestInterface
    {
        return self::create($method, $path, (string) json_encode(
            $payload,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        ), ['Content-Type' => 'application/json', 'Accept' => 'application/json']);
    }
}
