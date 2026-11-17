<?php

declare(strict_types=1);

namespace Itqan\CommunityMcp\Mcp;

use Flarum\Settings\SettingsRepositoryInterface;
use Flarum\User\User;
use InvalidArgumentException;
use Itqan\CommunityMcp\Mcp\Prompts\ResearchTopicPrompt;
use Itqan\CommunityMcp\Mcp\Resources\ForumOverviewResource;
use Itqan\CommunityMcp\Mcp\Resources\ForumTaxonomyResource;
use Itqan\CommunityMcp\Mcp\Tools\GetDiscussionTool;
use Itqan\CommunityMcp\Mcp\Tools\GetUserProfileAndPostsTool;
use Itqan\CommunityMcp\Mcp\Tools\ListTagsTool;
use Itqan\CommunityMcp\Mcp\Tools\SearchDiscussionsTool;
use Itqan\CommunityMcp\Mcp\Tools\SearchPostsTool;
use Itqan\CommunityMcp\Mcp\Tools\ToolInterface;
use Itqan\CommunityMcp\Search\ForumSearchService;
use Itqan\CommunityMcp\Support\ArraySettings;
use RuntimeException;

/**
 * Stateless JSON-RPC 2.0 dispatcher for the Itqan Community MCP server.
 *
 * It owns the three MCP primitives exposed by this extension:
 *
 * - tools     — `list_tags`, `search_discussions`, `get_discussion`,
 *               `search_posts`, `get_user_contributions`
 * - resources  — `itqan://overview`, `itqan://tags`
 * - prompts    — `research_quranic_tech_topic`
 *
 * Both a single JSON-RPC message and a batch array are supported. A
 * notification (a valid request without an `id` member) produces no response,
 * which is why `dispatchPayload()` and `dispatchMessage()` return `?array`.
 *
 * The constructor can be satisfied by Flarum's IoC container with only the
 * shared `ForumSearchService`, while tests may pass their own settings object,
 * tools, resources or prompt.
 */
class JsonRpcDispatcher
{
    public const JSONRPC_VERSION = '2.0';

    public const PARSE_ERROR = -32700;

    public const INVALID_REQUEST = -32600;

    public const METHOD_NOT_FOUND = -32601;

    public const INVALID_PARAMS = -32602;

    public const INTERNAL_ERROR = -32603;

    /**
     * A JSON-RPC batch is a single HTTP request as far as the rate limiter is
     * concerned, so it must not be usable as an amplifier: one request may
     * never trigger an unbounded number of tool calls.
     */
    public const MAX_BATCH_SIZE = 10;

    /**
     * Request bodies above this size are rejected before decoding, so a large
     * payload cannot be used to burn memory or CPU in one rate-limited slot.
     */
    public const MAX_PAYLOAD_BYTES = 65536;

    private SettingsRepositoryInterface $settings;

    /**
     * @var array<string, ToolInterface>
     */
    private array $tools = [];

    private ForumOverviewResource $overview;

    private ForumTaxonomyResource $taxonomy;

    private ResearchTopicPrompt $researchPrompt;

    /**
     * @param array<int, ToolInterface>|null $tools
     */
    public function __construct(
        ForumSearchService $searchService,
        ?SettingsRepositoryInterface $settings = null,
        ?array $tools = null,
        ?ForumOverviewResource $overview = null,
        ?ForumTaxonomyResource $taxonomy = null,
        ?ResearchTopicPrompt $prompt = null
    ) {
        $this->settings = $settings ?? self::resolveSettings();

        $toolList = $tools ?? [
            new ListTagsTool($searchService),
            new SearchDiscussionsTool($searchService),
            new GetDiscussionTool($searchService),
            new SearchPostsTool($searchService),
            new GetUserProfileAndPostsTool($searchService),
        ];

        foreach ($toolList as $tool) {
            if ($tool instanceof ToolInterface) {
                $this->tools[$tool->getName()] = $tool;
            }
        }

        $this->overview = $overview ?? new ForumOverviewResource($searchService, $this->settings);
        $this->taxonomy = $taxonomy ?? new ForumTaxonomyResource($searchService, new ListTagsTool($searchService));
        $this->researchPrompt = $prompt ?? new ResearchTopicPrompt();
    }

    // ------------------------------------------------------------------
    // Entry points
    // ------------------------------------------------------------------

    /**
     * Decode and dispatch a raw JSON body.
     *
     * @return array<string, mixed>|array<int, array<string, mixed>>|null
     */
    public function dispatchPayload(string $rawBody, User $actor): ?array
    {
        if (strlen($rawBody) > self::MAX_PAYLOAD_BYTES) {
            return $this->errorResponse(
                null,
                self::INVALID_REQUEST,
                sprintf('Invalid Request: Payload exceeds the maximum of %d bytes.', self::MAX_PAYLOAD_BYTES)
            );
        }

        $decoded = json_decode($rawBody, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return $this->errorResponse(null, self::PARSE_ERROR, 'Parse error');
        }

        if (! is_array($decoded) || $decoded === []) {
            return $this->errorResponse(null, self::INVALID_REQUEST, 'Invalid Request');
        }

        // A JSON array is a batch; a JSON object decodes to an associative
        // array and is a single message. `array_is_list()` is available on
        // PHP 8.1, the minimum supported by this extension.
        if (array_is_list($decoded)) {
            if (count($decoded) > self::MAX_BATCH_SIZE) {
                return $this->errorResponse(
                    null,
                    self::INVALID_REQUEST,
                    sprintf('Invalid Request: Batch size exceeds maximum of %d.', self::MAX_BATCH_SIZE)
                );
            }

            $responses = [];

            foreach ($decoded as $message) {
                if (! is_array($message)) {
                    $responses[] = $this->errorResponse(null, self::INVALID_REQUEST, 'Invalid Request');

                    continue;
                }

                $response = $this->dispatchMessage($message, $actor);

                if ($response !== null) {
                    $responses[] = $response;
                }
            }

            return $responses === [] ? null : $responses;
        }

        return $this->dispatchMessage($decoded, $actor);
    }

    /**
     * Dispatch one JSON-RPC message, or return null for a notification.
     *
     * @param array<string, mixed> $message
     * @return array<string, mixed>|null
     */
    public function dispatchMessage(array $message, User $actor): ?array
    {
        $hasId = array_key_exists('id', $message);
        $id = $message['id'] ?? null;

        if (($message['jsonrpc'] ?? null) !== self::JSONRPC_VERSION || ! is_string($message['method'] ?? null)) {
            // A malformed message can never be a notification: no reliable
            // method name means no reliable notification semantics either.
            return $this->errorResponse($hasId ? $id : null, self::INVALID_REQUEST, 'Invalid Request');
        }

        $params = $message['params'] ?? [];

        if (! is_array($params)) {
            return $hasId
                ? $this->errorResponse($id, self::INVALID_PARAMS, 'Invalid params')
                : null;
        }

        try {
            $result = $this->handleMethod($message['method'], $params, $actor);
        } catch (JsonRpcException $e) {
            return $hasId
                ? $this->errorResponse($id, $e->getRpcCode(), $e->getMessage())
                : null;
        }

        if (! $hasId) {
            return null;
        }

        return [
            'jsonrpc' => self::JSONRPC_VERSION,
            'id' => $id,
            'result' => $result,
        ];
    }

    // ------------------------------------------------------------------
    // Public introspection (used by GET /api/mcp discovery)
    // ------------------------------------------------------------------

    /**
     * @return array<string, ToolInterface>
     */
    public function getTools(): array
    {
        return $this->tools;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getToolDefinitions(): array
    {
        $definitions = [];

        foreach ($this->tools as $tool) {
            $definitions[] = [
                'name' => $tool->getName(),
                'description' => $tool->getDescription(),
                'inputSchema' => $this->normalizeSchema($tool->getInputSchema()),
                'annotations' => $tool->getAnnotations(),
            ];
        }

        return $definitions;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getResourceDefinitions(): array
    {
        return [
            $this->overview->toResourceDefinition(),
            $this->taxonomy->toResourceDefinition(),
        ];
    }

    // ------------------------------------------------------------------
    // Method routing
    // ------------------------------------------------------------------

    /**
     * @param array<string, mixed> $params
     * @return mixed
     */
    private function handleMethod(string $method, array $params, User $actor)
    {
        return match ($method) {
            'initialize' => $this->handleInitialize($params),
            'ping' => (object) [],
            'tools/list' => ['tools' => $this->getToolDefinitions()],
            'tools/call' => $this->handleToolCall($params, $actor),
            'resources/list' => ['resources' => $this->getResourceDefinitions()],
            'resources/templates/list' => ['resourceTemplates' => []],
            'resources/read' => $this->handleResourceRead($params, $actor),
            'prompts/list' => ['prompts' => [$this->researchPrompt->toPromptDefinition()]],
            'prompts/get' => $this->handlePromptGet($params),
            default => throw new JsonRpcException(self::METHOD_NOT_FOUND, 'Method not found'),
        };
    }

    /**
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    private function handleInitialize(array $params): array
    {
        $requested = $params['protocolVersion'] ?? null;

        return [
            'protocolVersion' => ProtocolVersion::negotiate(is_string($requested) ? $requested : null),
            'capabilities' => ServerCapabilities::getCapabilities(),
            'serverInfo' => ServerCapabilities::getServerInfo(),
            'instructions' => ServerCapabilities::getInstructions(),
        ];
    }

    /**
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    private function handleToolCall(array $params, User $actor): array
    {
        $name = $params['name'] ?? null;

        if (! is_string($name) || $name === '' || ! isset($this->tools[$name])) {
            throw new JsonRpcException(self::INVALID_PARAMS, 'Invalid params: unknown or missing tool `name`.');
        }

        $tool = $this->tools[$name];
        $arguments = $params['arguments'] ?? [];

        if (! is_array($arguments)) {
            throw new JsonRpcException(self::INVALID_PARAMS, 'Invalid params: `arguments` must be an object.');
        }

        $missing = $this->missingRequiredArguments($tool, $arguments);

        if ($missing !== []) {
            return $this->toolError(sprintf(
                'Missing required argument(s): %s.',
                implode(', ', array_map(static fn (string $key): string => '`'.$key.'`', $missing))
            ));
        }

        try {
            $markdown = $tool->execute($arguments, $actor);
        } catch (InvalidArgumentException|RuntimeException $e) {
            // Domain errors (missing discussion, blank query, unknown user)
            // belong to the model: it can correct the call and retry.
            return $this->toolError($e->getMessage());
        }

        return [
            'content' => [
                [
                    'type' => 'text',
                    'text' => (string) $markdown,
                ],
            ],
            'isError' => false,
        ];
    }

    /**
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    private function handleResourceRead(array $params, User $actor): array
    {
        $uri = $params['uri'] ?? null;

        if (! is_string($uri) || $uri === '') {
            throw new JsonRpcException(self::INVALID_PARAMS, 'Invalid params: `uri` is required.');
        }

        $resource = match ($uri) {
            ForumOverviewResource::URI => $this->overview,
            ForumTaxonomyResource::URI => $this->taxonomy,
            default => throw new JsonRpcException(self::INVALID_PARAMS, 'Unknown resource URI: '.$uri),
        };

        return ['contents' => [$resource->read($actor)]];
    }

    /**
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    private function handlePromptGet(array $params): array
    {
        $name = $params['name'] ?? null;

        if ($name !== ResearchTopicPrompt::NAME) {
            throw new JsonRpcException(
                self::INVALID_PARAMS,
                'Invalid params: unknown prompt `'.(is_string($name) ? $name : '').'`.'
            );
        }

        $arguments = $params['arguments'] ?? [];

        if (! is_array($arguments)) {
            throw new JsonRpcException(self::INVALID_PARAMS, 'Invalid params: `arguments` must be an object.');
        }

        try {
            return $this->researchPrompt->getMessages($arguments);
        } catch (InvalidArgumentException $e) {
            throw new JsonRpcException(self::INVALID_PARAMS, $e->getMessage());
        }
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    /**
     * @param array<string, mixed> $arguments
     * @return array<int, string>
     */
    private function missingRequiredArguments(ToolInterface $tool, array $arguments): array
    {
        $missing = [];

        foreach ($tool->getInputSchema()['required'] ?? [] as $key) {
            if (! is_string($key) || $key === '') {
                continue;
            }

            if (! array_key_exists($key, $arguments) || $arguments[$key] === null || $arguments[$key] === '') {
                $missing[] = $key;
            }
        }

        return $missing;
    }

    /**
     * MCP clients expect `properties` to be a JSON object even when a tool
     * takes no arguments; a PHP empty array would encode as `[]`.
     *
     * @param array<string, mixed> $schema
     * @return array<string, mixed>
     */
    private function normalizeSchema(array $schema): array
    {
        if (isset($schema['properties']) && is_array($schema['properties']) && $schema['properties'] === []) {
            $schema['properties'] = (object) [];
        }

        return $schema;
    }

    /**
     * @return array<string, mixed>
     */
    private function toolError(string $message): array
    {
        return [
            'content' => [
                [
                    'type' => 'text',
                    'text' => $message,
                ],
            ],
            'isError' => true,
        ];
    }

    /**
     * @param mixed $id
     * @return array<string, mixed>
     */
    private function errorResponse($id, int $code, string $message): array
    {
        return [
            'jsonrpc' => self::JSONRPC_VERSION,
            'id' => $id,
            'error' => [
                'code' => $code,
                'message' => $message,
            ],
        ];
    }

    private static function resolveSettings(): SettingsRepositoryInterface
    {
        try {
            if (function_exists('resolve')) {
                $settings = resolve(SettingsRepositoryInterface::class);

                if ($settings instanceof SettingsRepositoryInterface) {
                    return $settings;
                }
            }
        } catch (\Throwable) {
            // Container not booted (tests, CLI helpers).
        }

        return new ArraySettings();
    }
}
