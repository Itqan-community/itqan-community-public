<?php

declare(strict_types=1);

require __DIR__.'/bootstrap.php';

use Flarum\Settings\SettingsRepositoryInterface;
use Flarum\User\Guest;
use Itqan\CommunityMcp\Http\Controller\DiscussionMarkdownController;
use Itqan\CommunityMcp\Http\Controller\LlmsTxtController;
use Itqan\CommunityMcp\Http\Controller\McpStreamableController;
use Itqan\CommunityMcp\Http\Controller\SkillMdController;
use Itqan\CommunityMcp\Http\Middleware\McpCorsAndRateLimitMiddleware;
use Itqan\CommunityMcp\Mcp\JsonRpcDispatcher;
use Itqan\CommunityMcp\Search\ForumSearchService;
use Itqan\CommunityMcp\Support\ArraySettings;
use Itqan\CommunityMcp\Tests\Support\FlarumApp;
use Laminas\Diactoros\Response\EmptyResponse;
use Laminas\Diactoros\ServerRequest;
use Laminas\Diactoros\Stream;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

$failures = 0;
$checks = 0;

function check(string $label, bool $condition, string $detail = ''): void
{
    global $failures, $checks;

    $checks++;

    if ($condition) {
        echo "PASS  {$label}\n";
    } else {
        $failures++;
        echo "FAIL  {$label}".($detail !== '' ? "\n      ".substr($detail, 0, 800) : '')."\n";
    }
}

function encode($data): string
{
    return (string) json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

function request(
    string $method,
    string $path,
    string $body = '',
    array $headers = [],
    string $remoteAddr = '203.0.113.9'
): ServerRequestInterface {
    $stream = new Stream('php://temp', 'wb+');
    $stream->write($body);
    $stream->rewind();

    $uri = 'http://localhost:8080'.$path;
    $query = [];
    $queryString = parse_url($uri, PHP_URL_QUERY);

    if (is_string($queryString)) {
        parse_str($queryString, $query);
    }

    return new ServerRequest(
        ['REMOTE_ADDR' => $remoteAddr],
        [],
        $uri,
        $method,
        $stream,
        $headers,
        [],
        $query
    );
}

/** @var \Flarum\Foundation\Application $app */
$app = FlarumApp::boot();

if ($app === null) {
    fwrite(STDERR, "Cannot boot Flarum: ".FlarumApp::error()."\n");
    exit(1);
}

$container = $app->getContainer();

/** @var ForumSearchService $search */
$search = $container->make(ForumSearchService::class);
$settings = $container->make(SettingsRepositoryInterface::class);
$dispatcher = new JsonRpcDispatcher($search, $settings);

$guest = new Guest();

echo "== JSON-RPC methods ==\n";

$response = $dispatcher->dispatchPayload(encode([
    'jsonrpc' => '2.0', 'id' => 1, 'method' => 'initialize',
    'params' => ['protocolVersion' => '2025-06-18'],
]), $guest);
check('initialize result', isset($response['result']['protocolVersion']) && $response['result']['protocolVersion'] === '2025-06-18', encode($response));
check('initialize serverInfo', ($response['result']['serverInfo']['name'] ?? '') === 'itqan-community-mcp');
check('initialize instructions', str_contains((string) ($response['result']['instructions'] ?? ''), 'إتقان'));

$response = $dispatcher->dispatchPayload(encode(['jsonrpc' => '2.0', 'id' => 2, 'method' => 'ping']), $guest);
check('ping returns empty object', is_array($response) && $response['result'] instanceof \stdClass, encode($response));
check('ping encodes as {}', str_contains(encode($response), '"result":{}'));

$response = $dispatcher->dispatchPayload(encode(['jsonrpc' => '2.0', 'id' => 3, 'method' => 'tools/list']), $guest);
$names = array_column($response['result']['tools'] ?? [], 'name');
check('tools/list has 5 tools', count($names) === 5, encode($names));
check('tools/list names', $names === ['list_tags', 'search_discussions', 'get_discussion', 'search_posts', 'get_user_contributions'], encode($names));
$schemasValid = true;

foreach ($response['result']['tools'] as $tool) {
    if (! isset($tool['inputSchema']['type'], $tool['inputSchema']['properties'], $tool['annotations'])) {
        $schemasValid = false;
    }
}

check('tools/list schemas + annotations', $schemasValid);

$response = $dispatcher->dispatchPayload(encode(['jsonrpc' => '2.0', 'id' => 4, 'method' => 'resources/list']), $guest);
$uris = array_column($response['result']['resources'] ?? [], 'uri');
check('resources/list', $uris === ['itqan://overview', 'itqan://tags'], encode($uris));

$response = $dispatcher->dispatchPayload(encode(['jsonrpc' => '2.0', 'id' => 5, 'method' => 'resources/templates/list']), $guest);
check('resources/templates/list', $response['result']['resourceTemplates'] === []);

$response = $dispatcher->dispatchPayload(encode(['jsonrpc' => '2.0', 'id' => 6, 'method' => 'prompts/list']), $guest);
check('prompts/list', ($response['result']['prompts'][0]['name'] ?? '') === 'research_quranic_tech_topic');

$response = $dispatcher->dispatchPayload(encode([
    'jsonrpc' => '2.0', 'id' => 7, 'method' => 'prompts/get',
    'params' => ['name' => 'research_quranic_tech_topic', 'arguments' => ['topic' => 'مصحف عماد']],
]), $guest);
check('prompts/get', isset($response['result']['messages'][0]['content']['text']));
check('prompts/get keeps Arabic unescaped', str_contains($response['result']['messages'][0]['content']['text'], 'مصحف عماد'));

$response = $dispatcher->dispatchPayload(encode([
    'jsonrpc' => '2.0', 'id' => 8, 'method' => 'resources/read', 'params' => ['uri' => 'itqan://tags'],
]), $guest);
check('resources/read tags', isset($response['result']['contents'][0]['text']) && $response['result']['contents'][0]['mimeType'] === 'text/markdown');
check('resources/read tags has taxonomy', str_contains($response['result']['contents'][0]['text'], 'الأقسام الرئيسية'));

$response = $dispatcher->dispatchPayload(encode([
    'jsonrpc' => '2.0', 'id' => 9, 'method' => 'resources/read', 'params' => ['uri' => 'itqan://nope'],
]), $guest);
check('unknown resource -> -32602', ($response['error']['code'] ?? 0) === -32602, encode($response));

$response = $dispatcher->dispatchPayload(encode(['jsonrpc' => '2.0', 'id' => 10, 'method' => 'nope']), $guest);
check('unknown method -> -32601', ($response['error']['code'] ?? 0) === -32601);

$response = $dispatcher->dispatchPayload(encode([
    'jsonrpc' => '2.0', 'id' => 11, 'method' => 'tools/call', 'params' => ['name' => 'nope'],
]), $guest);
check('unknown tool -> -32602', ($response['error']['code'] ?? 0) === -32602);

$response = $dispatcher->dispatchPayload(encode([
    'jsonrpc' => '2.0', 'id' => 12, 'method' => 'tools/call',
    'params' => ['name' => 'get_discussion', 'arguments' => []],
]), $guest);
check('missing required tool arg -> isError', ($response['result']['isError'] ?? null) === true, encode($response));

$response = $dispatcher->dispatchPayload(encode([
    'jsonrpc' => '2.0', 'id' => 13, 'method' => 'tools/call',
    'params' => ['name' => 'list_tags', 'arguments' => ['include_empty' => true]],
]), $guest);
$text = $response['result']['content'][0]['text'] ?? '';
check('tools/call list_tags', ($response['result']['isError'] ?? null) === false && str_contains($text, 'الأقسام الرئيسية'), substr($text, 0, 300));
check('tools/call keeps unescaped Arabic', str_contains($text, 'مجتمع إتقان'));

$discussionId = (int) $container->make('db')->table('discussions')->whereNull('hidden_at')->orderByDesc('comment_count')->value('id');

if ($discussionId > 0) {
    $response = $dispatcher->dispatchPayload(encode([
        'jsonrpc' => '2.0', 'id' => 14, 'method' => 'tools/call',
        'params' => ['name' => 'get_discussion', 'arguments' => ['discussion_id' => $discussionId, 'post_limit' => 2]],
    ]), $guest);
    $text = $response['result']['content'][0]['text'] ?? '';
    check('tools/call get_discussion', ($response['result']['isError'] ?? null) === false && str_contains($text, 'معرف المناقشة'), substr($text, 0, 300));

    $response = $dispatcher->dispatchPayload(encode([
        'jsonrpc' => '2.0', 'id' => 15, 'method' => 'tools/call',
        'params' => ['name' => 'get_discussion', 'arguments' => ['discussion_id' => 99999999]],
    ]), $guest);
    check('get_discussion unknown -> isError', ($response['result']['isError'] ?? null) === true, encode($response));
} else {
    echo "SKIP  no discussions in database\n";
}

echo "\n== Notifications, batches, errors ==\n";

check('notification returns null', $dispatcher->dispatchPayload(encode([
    'jsonrpc' => '2.0', 'method' => 'notifications/initialized',
]), $guest) === null);

check('initialized notification returns null', $dispatcher->dispatchPayload(encode([
    'jsonrpc' => '2.0', 'method' => 'initialized',
]), $guest) === null);

check('cancelled notification returns null', $dispatcher->dispatchPayload(encode([
    'jsonrpc' => '2.0', 'method' => 'notifications/cancelled', 'params' => ['requestId' => 1],
]), $guest) === null);

check('id:null is a request', is_array($dispatcher->dispatchPayload(encode([
    'jsonrpc' => '2.0', 'id' => null, 'method' => 'ping',
]), $guest)));

$response = $dispatcher->dispatchPayload('{not json', $guest);
check('parse error -> -32700', ($response['error']['code'] ?? 0) === -32700 && array_key_exists('id', $response) && $response['id'] === null);

$response = $dispatcher->dispatchPayload('{}', $guest);
check('empty object -> -32600', ($response['error']['code'] ?? 0) === -32600);

$response = $dispatcher->dispatchPayload('[]', $guest);
check('empty batch -> -32600', ($response['error']['code'] ?? 0) === -32600);

$response = $dispatcher->dispatchPayload(encode(['jsonrpc' => '1.0', 'id' => 20, 'method' => 'ping']), $guest);
check('wrong jsonrpc -> -32600', ($response['error']['code'] ?? 0) === -32600);

$response = $dispatcher->dispatchPayload(encode([
    ['jsonrpc' => '2.0', 'id' => 'a', 'method' => 'ping'],
    ['jsonrpc' => '2.0', 'method' => 'notifications/initialized'],
    ['jsonrpc' => '2.0', 'id' => 'b', 'method' => 'tools/list'],
]), $guest);
check('batch returns only request responses', is_array($response) && count($response) === 2 && $response[0]['id'] === 'a' && $response[1]['id'] === 'b', encode($response));

check('batch of only notifications -> null', $dispatcher->dispatchPayload(encode([
    ['jsonrpc' => '2.0', 'method' => 'notifications/initialized'],
    ['jsonrpc' => '2.0', 'method' => 'notifications/cancelled'],
]), $guest) === null);

$response = $dispatcher->dispatchPayload(encode([1, 2]), $guest);
check('batch of scalars -> errors', is_array($response) && count($response) === 2 && ($response[0]['error']['code'] ?? 0) === -32600);

echo "\n== Controllers ==\n";

$controller = $container->make(McpStreamableController::class);
$response = $controller->handle(request('POST', '/api/mcp', encode([
    'jsonrpc' => '2.0', 'id' => 30, 'method' => 'initialize', 'params' => ['protocolVersion' => '2025-03-26'],
])));
check('POST controller 200', $response->getStatusCode() === 200);
check('POST controller JSON content type', str_contains($response->getHeaderLine('Content-Type'), 'application/json'));
check('POST controller CORS', $response->getHeaderLine('Access-Control-Allow-Origin') === '*');
check('POST controller protocol version header', $response->getHeaderLine('MCP-Protocol-Version') === '2025-03-26');
check('POST controller keeps Arabic readable', ! str_contains((string) $response->getBody(), '\\u'));

$response = $controller->handle(request('POST', '/api/mcp', encode([
    'jsonrpc' => '2.0', 'method' => 'notifications/initialized',
])));
check('notification POST -> 202 empty', $response->getStatusCode() === 202 && (string) $response->getBody() === '');

$response = $controller->handle(request('GET', '/api/mcp', '', ['Accept' => 'application/json']));
$discovery = json_decode((string) $response->getBody(), true);
check('GET discovery 200', $response->getStatusCode() === 200);
check('GET discovery name', ($discovery['name'] ?? '') === 'itqan-community-mcp');
check('GET discovery endpoint', ($discovery['endpoint'] ?? '') === 'http://localhost:8080/api/mcp');
check('GET discovery tools', count($discovery['tools'] ?? []) === 5);
check('GET discovery pretty printed', str_contains((string) $response->getBody(), "\n    \"name\""));

$response = $controller->handle(request('GET', '/api/mcp', '', ['Accept' => 'text/event-stream']));
check('GET SSE-only -> 405', $response->getStatusCode() === 405 && $response->getHeaderLine('Allow') === 'POST, GET, OPTIONS');

$response = $controller->handle(request('OPTIONS', '/api/mcp'));
check('OPTIONS -> 204', $response->getStatusCode() === 204);
check('OPTIONS CORS headers', $response->getHeaderLine('Access-Control-Allow-Methods') === 'GET, POST, OPTIONS');

$response = $controller->handle(request('DELETE', '/api/mcp'));
check('unsupported method -> 405', $response->getStatusCode() === 405);

$markdownController = $container->make(DiscussionMarkdownController::class);

if ($discussionId > 0) {
    $response = $markdownController->handle(
        request('GET', '/api/mcp/discussions/'.$discussionId.'.md?post_limit=2')
            ->withQueryParams(['id' => (string) $discussionId, 'post_limit' => '2'])
    );
    $text = (string) $response->getBody();
    check('discussion markdown 200', $response->getStatusCode() === 200, $text);
    check('discussion markdown content type', $response->getHeaderLine('Content-Type') === 'text/markdown; charset=utf-8');
    check('discussion markdown body', str_contains($text, '#') && str_contains($text, 'معرف المناقشة'));
    check('discussion markdown nosniff', $response->getHeaderLine('X-Content-Type-Options') === 'nosniff');

    $response = $markdownController->handle(
        request('GET', '/api/mcp/discussions/'.$discussionId.'-some-slug.md')
            ->withQueryParams(['id' => $discussionId.'-some-slug.md'])
    );
    check('slug suffix resolves id', $response->getStatusCode() === 200);
}

$response = $markdownController->handle(request('GET', '/api/mcp/discussions/99999999.md'));
check('missing discussion -> 404 markdown', $response->getStatusCode() === 404 && str_contains((string) $response->getBody(), '404'));

$llms = $container->make(LlmsTxtController::class);
$response = $llms->handle(request('GET', '/llms.txt'));
$text = (string) $response->getBody();
check('llms.txt 200', $response->getStatusCode() === 200);
check('llms.txt content type', str_contains($response->getHeaderLine('Content-Type'), 'text/plain'));
check('llms.txt MCP section', str_contains($text, 'AI & MCP Integration') && str_contains($text, '/api/mcp/discussions/{id}.md'));
check('llms.txt tags section', str_contains($text, 'الأقسام والوسوم الرئيسية'));
check('llms.txt featured section', str_contains($text, 'Featured & Recent Discussions'));

$skill = $container->make(SkillMdController::class);
$response = $skill->handle(request('GET', '/api/mcp/SKILL.md'));
check('SKILL.md 200', $response->getStatusCode() === 200 && str_contains((string) $response->getBody(), 'Itqan Community MCP'));

echo "\n== Middleware ==\n";

$cache = new \Illuminate\Cache\Repository(new \Illuminate\Cache\ArrayStore());
$mw = new McpCorsAndRateLimitMiddleware($cache, new ArraySettings([
    'itqan-community-mcp.enabled' => '1',
    'itqan-community-mcp.rate_limit_per_minute' => 2,
]));

$terminal = new class implements RequestHandlerInterface {
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        return new EmptyResponse(200);
    }
};

$response = $mw->process(request('GET', '/api/discussions'), $terminal);
check('middleware passthrough non-MCP path', $response->getStatusCode() === 200 && $response->getHeaderLine('Access-Control-Allow-Origin') === '');

$response = $mw->process(request('OPTIONS', '/api/mcp'), $terminal);
check('middleware preflight 204 + CORS', $response->getStatusCode() === 204 && $response->getHeaderLine('Access-Control-Max-Age') === '86400');

$response = $mw->process(request('POST', '/api/mcp'), $terminal);
check('middleware first request allowed', $response->getStatusCode() === 200 && $response->getHeaderLine('X-RateLimit-Remaining') === '1');
$response = $mw->process(request('POST', '/api/mcp'), $terminal);
check('middleware second request allowed', $response->getStatusCode() === 200 && $response->getHeaderLine('X-RateLimit-Remaining') === '0');
$response = $mw->process(request('POST', '/api/mcp'), $terminal);
$error = json_decode((string) $response->getBody(), true);
check('middleware third request 429', $response->getStatusCode() === 429 && ($error['error']['code'] ?? 0) === -32029);
check('middleware 429 Retry-After', $response->getHeaderLine('Retry-After') === '60');

$denied = new McpCorsAndRateLimitMiddleware(new \Illuminate\Cache\Repository(new \Illuminate\Cache\ArrayStore()), new ArraySettings([
    'itqan-community-mcp.enabled' => '0',
]));
$response = $denied->process(request('POST', '/api/mcp'), $terminal);
check('middleware disabled -> 503', $response->getStatusCode() === 503 && (json_decode((string) $response->getBody(), true)['error']['code'] ?? 0) === -32000);

$forwarded = new McpCorsAndRateLimitMiddleware($cache, new ArraySettings([
    'itqan-community-mcp.rate_limit_per_minute' => 1,
]));
$forwardedRequest = request(
    'GET',
    '/llms.txt',
    '',
    ['X-Forwarded-For' => '198.51.100.7, 10.0.0.1'],
    '127.0.0.1'
);
$response = $forwarded->process($forwardedRequest, $terminal);
check('middleware forwarded IP allowed behind trusted proxy', $response->getStatusCode() === 200);
$response = $forwarded->process($forwardedRequest, $terminal);
check('middleware forwarded IP limited', $response->getStatusCode() === 429);

// A direct public client cannot rotate X-Forwarded-For to escape its bucket.
$spoofed = request('GET', '/llms.txt', '', ['X-Forwarded-For' => '1.1.1.1'], '198.18.0.200');
$response = $forwarded->process($spoofed, $terminal);
check('middleware spoofed first XFF allowed', $response->getStatusCode() === 200);
$spoofed = request('GET', '/llms.txt', '', ['X-Forwarded-For' => '2.2.2.2'], '198.18.0.200');
$response = $forwarded->process($spoofed, $terminal);
check('middleware spoofed XFF does not rotate the bucket', $response->getStatusCode() === 429);

echo "\n== Full HTTP stack (routes, CSRF, middleware) ==\n";

$handler = $app->getRequestHandler();

$response = $handler->handle(request('POST', '/api/mcp', encode([
    'jsonrpc' => '2.0', 'id' => 40, 'method' => 'tools/list',
])));
$decoded = json_decode((string) $response->getBody(), true);
check('route POST /api/mcp works (CSRF exempt)', $response->getStatusCode() === 200 && count($decoded['result']['tools'] ?? []) === 5, (string) $response->getBody());
check('route adds rate limit headers', $response->getHeaderLine('X-RateLimit-Limit') === '60');
check('route adds CORS', $response->getHeaderLine('Access-Control-Allow-Origin') === '*');

$response = $handler->handle(request('OPTIONS', '/api/mcp'));
check('route OPTIONS preflight', $response->getStatusCode() === 204 && $response->getHeaderLine('Access-Control-Allow-Origin') === '*');

$response = $handler->handle(request('GET', '/api/mcp', '', ['Accept' => 'application/json']));
check('route GET /api/mcp discovery', $response->getStatusCode() === 200 && str_contains((string) $response->getBody(), 'itqan-community-mcp'));

$response = $handler->handle(request('GET', '/llms.txt'));
check('route GET /llms.txt', $response->getStatusCode() === 200 && str_contains((string) $response->getBody(), 'Itqan Community'));

$response = $handler->handle(request('GET', '/'));
check('forum page passes through untouched', $response->getStatusCode() === 200 && $response->getHeaderLine('Access-Control-Allow-Origin') === '');

$response = $handler->handle(request('GET', '/api/mcp/SKILL.md'));
check('route GET /api/mcp/SKILL.md', $response->getStatusCode() === 200 && str_contains((string) $response->getBody(), 'Itqan Community MCP'));

if ($discussionId > 0) {
    $response = $handler->handle(request('GET', '/api/mcp/discussions/'.$discussionId.'.md?post_limit=1'));
    check('route discussion markdown', $response->getStatusCode() === 200 && $response->getHeaderLine('Content-Type') === 'text/markdown; charset=utf-8');
}

echo "\n{$checks} checks, {$failures} failure(s)\n";

exit($failures === 0 ? 0 : 1);
