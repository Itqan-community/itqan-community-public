<?php

declare(strict_types=1);

namespace Itqan\CommunityMcp\Http\Controller;

use Flarum\Http\RequestUtil;
use Flarum\User\Guest;
use Flarum\User\User;
use Itqan\CommunityMcp\Http\CorsHeaders;
use Itqan\CommunityMcp\Mcp\JsonRpcDispatcher;
use Itqan\CommunityMcp\Mcp\ProtocolVersion;
use Itqan\CommunityMcp\Mcp\ServerCapabilities;
use Itqan\CommunityMcp\Search\ForumSearchService;
use Laminas\Diactoros\Response\EmptyResponse;
use Laminas\Diactoros\Response\TextResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Stateless Streamable HTTP endpoint for the MCP server.
 *
 * - `POST /api/mcp` — JSON-RPC 2.0 request or batch (and notifications → 202)
 * - `GET  /api/mcp` — human/model readable discovery document
 * - `OPTIONS /api/mcp` — CORS preflight
 *
 * Server-sent events are deliberately not offered: every MCP call in this
 * extension is a single, bounded read, so the client gets the complete answer
 * in the POST response and never has to keep a stream open.
 */
class McpStreamableController implements RequestHandlerInterface
{
    private JsonRpcDispatcher $dispatcher;

    private ForumSearchService $search;

    public function __construct(JsonRpcDispatcher $dispatcher, ForumSearchService $searchService)
    {
        $this->dispatcher = $dispatcher;
        $this->search = $searchService;
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $method = strtoupper($request->getMethod());

        return match ($method) {
            'OPTIONS' => CorsHeaders::apply(new EmptyResponse(204)),
            'GET' => $this->handleGet($request),
            'POST' => $this->handlePost($request),
            default => $this->jsonResponse(
                405,
                [
                    'jsonrpc' => '2.0',
                    'id' => null,
                    'error' => [
                        'code' => -32000,
                        'message' => 'Method not allowed. Use POST /api/mcp for JSON-RPC 2.0 requests, or GET /api/mcp for discovery.',
                    ],
                ],
                ['Allow' => 'POST, GET, OPTIONS']
            ),
        };
    }

    private function handleGet(ServerRequestInterface $request): ResponseInterface
    {
        $accept = strtolower($request->getHeaderLine('Accept'));

        // A client that insists on SSE before JSON is asking for the streaming
        // transport, which this stateless server does not implement.
        if (str_contains($accept, 'text/event-stream') && ! str_contains($accept, 'application/json')) {
            return $this->jsonResponse(
                405,
                [
                    'jsonrpc' => '2.0',
                    'id' => null,
                    'error' => [
                        'code' => -32000,
                        'message' => 'This MCP server uses Stateless Streamable HTTP. Send JSON-RPC 2.0 requests with POST /api/mcp; server-sent events are not offered.',
                    ],
                ],
                ['Allow' => 'POST, GET, OPTIONS']
            );
        }

        $baseUrl = $this->search->getBaseUrl();
        $tools = [];

        foreach ($this->dispatcher->getTools() as $tool) {
            $tools[] = [
                'name' => $tool->getName(),
                'description' => $tool->getDescription(),
                'annotations' => $tool->getAnnotations(),
            ];
        }

        return $this->jsonResponse(200, [
            'name' => ServerCapabilities::SERVER_NAME,
            'version' => ServerCapabilities::SERVER_VERSION,
            'protocolVersions' => ProtocolVersion::SUPPORTED,
            'endpoint' => $baseUrl.'/api/mcp',
            'markdownDiscussionEndpoint' => $baseUrl.'/api/mcp/discussions/{id}.md',
            'skillEndpoint' => $baseUrl.'/api/mcp/SKILL.md',
            'llmsTxtEndpoint' => $baseUrl.'/llms.txt',
            'capabilities' => ServerCapabilities::getCapabilities(),
            'tools' => $tools,
        ], [], true);
    }

    private function handlePost(ServerRequestInterface $request): ResponseInterface
    {
        // Reject an obviously oversized body before reading it into memory.
        // The dispatcher still enforces the real length after the read.
        $declared = $request->getHeaderLine('Content-Length');

        if ($declared !== '' && ctype_digit($declared) && (int) $declared > JsonRpcDispatcher::MAX_PAYLOAD_BYTES) {
            return $this->jsonResponse(200, [
                'jsonrpc' => '2.0',
                'id' => null,
                'error' => [
                    'code' => JsonRpcDispatcher::INVALID_REQUEST,
                    'message' => sprintf(
                        'Invalid Request: Payload exceeds the maximum of %d bytes.',
                        JsonRpcDispatcher::MAX_PAYLOAD_BYTES
                    ),
                ],
            ]);
        }

        $rawBody = (string) $request->getBody();
        $result = $this->dispatcher->dispatchPayload($rawBody, $this->actor($request));

        if ($result === null) {
            // Notifications (for example `notifications/initialized`) get an
            // empty 202 and never a JSON-RPC response.
            return CorsHeaders::apply(new EmptyResponse(202, ['X-Content-Type-Options' => 'nosniff']));
        }

        $json = json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        if ($json === false) {
            $json = json_encode([
                'jsonrpc' => '2.0',
                'id' => null,
                'error' => [
                    'code' => -32603,
                    'message' => 'Internal error: failed to encode the JSON-RPC response.',
                ],
            ]);
        }

        return $this->jsonResponse(
            200,
            null,
            ['MCP-Protocol-Version' => $this->negotiatedVersion($result)],
            false,
            (string) $json
        );
    }

    /**
     * The acting user, with a guest fallback for requests built in tests.
     */
    private function actor(ServerRequestInterface $request): User
    {
        try {
            if ($request->getAttribute('actorReference') !== null) {
                return RequestUtil::getActor($request);
            }
        } catch (\Throwable) {
            // Fall through to the other actor representations.
        }

        $actor = $request->getAttribute('actor');

        if ($actor instanceof User) {
            return $actor;
        }

        return new Guest();
    }

    /**
     * @param array<string, mixed>|array<int, array<string, mixed>> $result
     */
    private function negotiatedVersion(array $result): string
    {
        $inner = $result['result'] ?? null;

        if (is_array($inner) && isset($inner['protocolVersion']) && is_string($inner['protocolVersion'])) {
            return $inner['protocolVersion'];
        }

        // `ping` returns an empty object (`stdClass`), not a result array.
        if (is_object($inner) && isset($inner->protocolVersion) && is_string($inner->protocolVersion)) {
            return $inner->protocolVersion;
        }

        return ProtocolVersion::LATEST;
    }

    /**
     * @param array<string, mixed>|null $payload
     * @param array<string, string> $headers
     */
    private function jsonResponse(
        int $status,
        ?array $payload = null,
        array $headers = [],
        bool $pretty = false,
        ?string $preEncoded = null
    ): ResponseInterface {
        if ($preEncoded === null) {
            $flags = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;

            if ($pretty) {
                $flags |= JSON_PRETTY_PRINT;
            }

            $body = json_encode($payload, $flags);

            if ($body === false) {
                $status = 500;
                $body = '{"jsonrpc":"2.0","id":null,"error":{"code":-32603,"message":"Internal error"}}';
            }
        } else {
            $body = $preEncoded;
        }

        $headers = array_merge([
            'Content-Type' => 'application/json; charset=utf-8',
            'Cache-Control' => 'no-store',
            'X-Content-Type-Options' => 'nosniff',
        ], $headers);

        return CorsHeaders::apply(new TextResponse($body, $status, $headers));
    }
}
