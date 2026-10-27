<?php

declare(strict_types=1);

namespace Itqan\CommunityMcp\Tests\Unit;

use Flarum\User\Guest;
use Illuminate\Cache\ArrayStore;
use Illuminate\Cache\Repository as CacheRepository;
use Itqan\CommunityMcp\Mcp\JsonRpcDispatcher;
use Itqan\CommunityMcp\Mcp\ProtocolVersion;
use Itqan\CommunityMcp\Search\ForumSearchService;
use Itqan\CommunityMcp\Support\ArraySettings;
use Itqan\CommunityMcp\Tests\Support\TestCase;

/**
 * Protocol-level tests for the JSON-RPC 2.0 dispatcher.
 *
 * Every method exercised here is independent of the database: the search
 * service is constructed lazily, so the dispatcher can be tested without a
 * booted Flarum application. Database-backed tool execution is covered by the
 * live HTTP checks and the integration suite.
 */
final class JsonRpcDispatcherTest extends TestCase
{
    private JsonRpcDispatcher $dispatcher;

    public function setUp(): void
    {
        $this->dispatcher = new JsonRpcDispatcher(
            new ForumSearchService(
                new CacheRepository(new ArrayStore()),
                new ArraySettings()
            ),
            new ArraySettings()
        );
    }

    public function testInitializeNegotiatesNewestSupportedVersion(): void
    {
        $response = $this->request('initialize', ['protocolVersion' => '2025-06-18']);

        $this->assertSame('2025-06-18', $response['result']['protocolVersion']);
        $this->assertSame('itqan-community-mcp', $response['result']['serverInfo']['name']);
        $this->assertSame('1.0.0', $response['result']['serverInfo']['version']);
        $this->assertStringContainsString('إتقان', $response['result']['instructions']);
    }

    public function testInitializeSupportsEveryAdvertisedProtocolVersion(): void
    {
        foreach (ProtocolVersion::SUPPORTED as $version) {
            $response = $this->request('initialize', ['protocolVersion' => $version]);

            $this->assertSame($version, $response['result']['protocolVersion'], 'Version '.$version.' must be echoed.');
        }
    }

    public function testInitializeFallsBackForUnsupportedVersion(): void
    {
        $response = $this->request('initialize', ['protocolVersion' => '2024-01-01']);
        $this->assertSame('2025-03-26', $response['result']['protocolVersion']);

        $response = $this->request('initialize', []);
        $this->assertSame('2025-03-26', $response['result']['protocolVersion']);
    }

    public function testInitializeAdvertisesCapabilities(): void
    {
        $capabilities = $this->request('initialize', [])['result']['capabilities'];

        $this->assertArrayHasKey('tools', $capabilities);
        $this->assertArrayHasKey('resources', $capabilities);
        $this->assertArrayHasKey('prompts', $capabilities);
        $this->assertFalse($capabilities['tools']['listChanged']);
    }

    public function testPingReturnsEmptyObject(): void
    {
        $response = $this->request('ping');

        $this->assertIsObject($response['result']);
        $this->assertSame([], (array) $response['result']);
        $this->assertStringContainsString(
            '"result":{}',
            (string) json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        );
    }

    public function testToolsListReturnsAllFiveTools(): void
    {
        $response = $this->request('tools/list');
        $names = array_column($response['result']['tools'], 'name');

        $this->assertCount(5, $names);
        $this->assertSame([
            'list_tags',
            'search_discussions',
            'get_discussion',
            'search_posts',
            'get_user_contributions',
        ], $names);
    }

    public function testToolsListExposesValidSchemasAndAnnotations(): void
    {
        $tools = $this->request('tools/list')['result']['tools'];

        foreach ($tools as $tool) {
            $this->assertArrayHasKey('name', $tool);
            $this->assertArrayHasKey('description', $tool);
            $this->assertArrayHasKey('inputSchema', $tool);
            $this->assertSame('object', $tool['inputSchema']['type'], $tool['name'].' inputSchema type');
            $this->assertArrayHasKey('properties', $tool['inputSchema']);
            $this->assertNotEmpty($tool['inputSchema']['properties'], $tool['name'].' must declare properties');
            $this->assertFalse($tool['inputSchema']['additionalProperties'] ?? true, $tool['name'].' must reject unknown arguments');
            $this->assertTrue($tool['annotations']['readOnlyHint'], $tool['name'].' must be read-only');

            foreach ($tool['inputSchema']['required'] ?? [] as $required) {
                $this->assertArrayHasKey($required, $tool['inputSchema']['properties'], $tool['name'].' required argument must be declared');
            }

            // Schemas must survive a JSON round-trip unchanged (aliases, enums,
            // numeric bounds) so clients can validate them.
            $encoded = json_encode($tool['inputSchema'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $this->assertIsString($encoded);
            $this->assertSame(
                $tool['inputSchema'],
                json_decode((string) $encoded, true)
            );
        }
    }

    public function testToolsListDeclaresRequiredArguments(): void
    {
        $byName = [];

        foreach ($this->request('tools/list')['result']['tools'] as $tool) {
            $byName[$tool['name']] = $tool;
        }

        $this->assertSame(['discussion_id'], $byName['get_discussion']['inputSchema']['required']);
        $this->assertSame(['query'], $byName['search_posts']['inputSchema']['required']);
        $this->assertSame(['username'], $byName['get_user_contributions']['inputSchema']['required']);
        $this->assertArrayNotHasKey('required', $byName['list_tags']['inputSchema']);
    }

    public function testResourcesList(): void
    {
        $response = $this->request('resources/list');
        $uris = array_column($response['result']['resources'], 'uri');

        $this->assertSame(['itqan://overview', 'itqan://tags'], $uris);

        foreach ($response['result']['resources'] as $resource) {
            $this->assertArrayHasKey('name', $resource);
            $this->assertArrayHasKey('description', $resource);
            $this->assertSame('text/markdown', $resource['mimeType']);
        }
    }

    public function testResourcesTemplatesListIsEmpty(): void
    {
        $response = $this->request('resources/templates/list');

        $this->assertSame([], $response['result']['resourceTemplates']);
    }

    public function testPromptsList(): void
    {
        $response = $this->request('prompts/list');
        $prompts = $response['result']['prompts'];

        $this->assertCount(1, $prompts);
        $this->assertSame('research_quranic_tech_topic', $prompts[0]['name']);
        $this->assertSame('topic', $prompts[0]['arguments'][0]['name']);
        $this->assertTrue($prompts[0]['arguments'][0]['required']);
        $this->assertSame('tag', $prompts[0]['arguments'][1]['name']);
        $this->assertFalse($prompts[0]['arguments'][1]['required']);
    }

    public function testPromptsGet(): void
    {
        $response = $this->request('prompts/get', [
            'name' => 'research_quranic_tech_topic',
            'arguments' => ['topic' => 'مصحف عماد'],
        ]);

        $text = $response['result']['messages'][0]['content']['text'];

        $this->assertSame('user', $response['result']['messages'][0]['role']);
        $this->assertStringContainsString('مصحف عماد', $text);
        $this->assertStringContainsString('list_tags', $text);
        $this->assertStringContainsString('search_discussions', $text);
    }

    public function testPromptsGetWithTagArgument(): void
    {
        $response = $this->request('prompts/get', [
            'name' => 'research_quranic_tech_topic',
            'arguments' => ['topic' => 'التعرف على الكلام', 'tag' => '#asr'],
        ]);

        $this->assertStringContainsString('`asr`', $response['result']['messages'][0]['content']['text']);
    }

    public function testPromptsGetRequiresTopic(): void
    {
        $response = $this->request('prompts/get', [
            'name' => 'research_quranic_tech_topic',
            'arguments' => [],
        ]);

        $this->assertSame(-32602, $response['error']['code']);
    }

    public function testPromptsGetRejectsUnknownPrompt(): void
    {
        $response = $this->request('prompts/get', ['name' => 'nope', 'arguments' => ['topic' => 'x']]);

        $this->assertSame(-32602, $response['error']['code']);
    }

    public function testNotificationsProduceNoResponse(): void
    {
        $this->assertNull($this->dispatch(['jsonrpc' => '2.0', 'method' => 'notifications/initialized']));
        $this->assertNull($this->dispatch(['jsonrpc' => '2.0', 'method' => 'initialized']));
        $this->assertNull($this->dispatch(['jsonrpc' => '2.0', 'method' => 'notifications/cancelled', 'params' => ['requestId' => 1]]));
    }

    public function testRequestWithNullIdIsNotANotification(): void
    {
        $response = $this->dispatch(['jsonrpc' => '2.0', 'id' => null, 'method' => 'ping']);

        $this->assertIsArray($response);
        $this->assertArrayHasKey('id', $response);
        $this->assertNull($response['id']);
    }

    public function testBatchReturnsOnlyRequestResponses(): void
    {
        $response = $this->dispatchPayload([
            ['jsonrpc' => '2.0', 'id' => 'a', 'method' => 'ping'],
            ['jsonrpc' => '2.0', 'method' => 'notifications/initialized'],
            ['jsonrpc' => '2.0', 'id' => 'b', 'method' => 'tools/list'],
        ]);

        $this->assertIsArray($response);
        $this->assertCount(2, $response);
        $this->assertSame('a', $response[0]['id']);
        $this->assertSame('b', $response[1]['id']);
    }

    public function testBatchOfOnlyNotificationsReturnsNull(): void
    {
        $response = $this->dispatchPayload([
            ['jsonrpc' => '2.0', 'method' => 'notifications/initialized'],
            ['jsonrpc' => '2.0', 'method' => 'notifications/cancelled'],
        ]);

        $this->assertNull($response);
    }

    public function testBatchOfScalarsReturnsInvalidRequestResponses(): void
    {
        $response = $this->dispatchPayload([1, 2]);

        $this->assertIsArray($response);
        $this->assertCount(2, $response);
        $this->assertSame(-32600, $response[0]['error']['code']);
        $this->assertSame(-32600, $response[1]['error']['code']);
    }

    public function testBatchAtTheLimitIsAccepted(): void
    {
        $batch = [];

        for ($i = 0; $i < JsonRpcDispatcher::MAX_BATCH_SIZE; $i++) {
            $batch[] = ['jsonrpc' => '2.0', 'id' => $i, 'method' => 'ping'];
        }

        $response = $this->dispatchPayload($batch);

        $this->assertIsArray($response);
        $this->assertCount(JsonRpcDispatcher::MAX_BATCH_SIZE, $response);
        $this->assertSame(0, $response[0]['id']);
    }

    public function testBatchSizeLimitBlocksAmplification(): void
    {
        // One HTTP request must never expand into an arbitrary number of
        // tool calls, otherwise the per-IP rate limiter counts one request.
        $batch = array_fill(0, JsonRpcDispatcher::MAX_BATCH_SIZE + 1, [
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => 'tools/call',
            'params' => ['name' => 'list_tags', 'arguments' => []],
        ]);

        $response = $this->dispatchPayload($batch);

        $this->assertIsArray($response);
        $this->assertArrayHasKey('error', $response);
        $this->assertSame(-32600, $response['error']['code']);
        $this->assertStringContainsString(
            'Batch size exceeds maximum of '.JsonRpcDispatcher::MAX_BATCH_SIZE,
            $response['error']['message']
        );
    }

    public function testOversizedPayloadIsRejectedBeforeDecoding(): void
    {
        $response = $this->raw(str_repeat('a', JsonRpcDispatcher::MAX_PAYLOAD_BYTES + 1));

        $this->assertIsArray($response);
        $this->assertSame(-32600, $response['error']['code']);
        $this->assertStringContainsString('Payload exceeds', $response['error']['message']);
        $this->assertNull($response['id']);
    }

    public function testParseError(): void
    {
        $response = $this->dispatcher->dispatchPayload('{not json', new Guest());

        $this->assertIsArray($response);
        $this->assertSame(-32700, $response['error']['code']);
        $this->assertSame('2.0', $response['jsonrpc']);
        $this->assertArrayHasKey('id', $response);
        $this->assertNull($response['id']);
    }

    public function testInvalidRequest(): void
    {
        $response = $this->raw('{}');

        $this->assertSame(-32600, $response['error']['code']);
    }

    public function testEmptyBatchIsInvalid(): void
    {
        $response = $this->raw('[]');

        $this->assertSame(-32600, $response['error']['code']);
    }

    public function testWrongJsonRpcVersionIsInvalidRequest(): void
    {
        $response = $this->dispatch(['jsonrpc' => '1.0', 'id' => 20, 'method' => 'ping']);

        $this->assertSame(-32600, $response['error']['code']);
    }

    public function testMissingMethodIsInvalidRequest(): void
    {
        $response = $this->dispatch(['jsonrpc' => '2.0', 'id' => 21]);

        $this->assertSame(-32600, $response['error']['code']);
    }

    public function testUnknownMethod(): void
    {
        $response = $this->request('nope');

        $this->assertSame(-32601, $response['error']['code']);
    }

    public function testUnknownTool(): void
    {
        $response = $this->request('tools/call', ['name' => 'nope']);

        $this->assertSame(-32602, $response['error']['code']);
    }

    public function testMissingRequiredToolArgument(): void
    {
        $response = $this->request('tools/call', ['name' => 'get_discussion', 'arguments' => []]);

        $this->assertTrue($response['result']['isError']);
        $this->assertStringContainsString('discussion_id', $response['result']['content'][0]['text']);

        $response = $this->request('tools/call', ['name' => 'search_posts', 'arguments' => ['query' => '']]);
        $this->assertTrue($response['result']['isError']);
        $this->assertStringContainsString('query', $response['result']['content'][0]['text']);
    }

    public function testNonObjectArgumentsAreInvalidParams(): void
    {
        $response = $this->request('tools/call', ['name' => 'list_tags', 'arguments' => 'not-an-object']);

        $this->assertSame(-32602, $response['error']['code']);
    }

    public function testUnknownResourceUri(): void
    {
        $response = $this->request('resources/read', ['uri' => 'itqan://nope']);

        $this->assertSame(-32602, $response['error']['code']);
    }

    public function testMissingResourceUri(): void
    {
        $response = $this->request('resources/read', []);

        $this->assertSame(-32602, $response['error']['code']);
    }

    /**
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    private function request(string $method, array $params = []): array
    {
        $message = ['jsonrpc' => '2.0', 'id' => 1, 'method' => $method];

        if ($params !== []) {
            $message['params'] = $params;
        }

        $response = $this->dispatch($message);
        $this->assertIsArray($response);

        return $response;
    }

    /**
     * @param array<mixed> $message
     * @return array<string, mixed>|array<int, array<string, mixed>>|null
     */
    private function dispatch(array $message): ?array
    {
        return $this->dispatcher->dispatchPayload(
            (string) json_encode($message, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            new Guest()
        );
    }

    /**
     * @param array<mixed> $message
     * @return array<string, mixed>|array<int, array<string, mixed>>|null
     */
    private function dispatchPayload(array $message): ?array
    {
        return $this->raw((string) json_encode($message, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    /**
     * @return array<string, mixed>|array<int, array<string, mixed>>|null
     */
    private function raw(string $payload): ?array
    {
        return $this->dispatcher->dispatchPayload($payload, new Guest());
    }
}
