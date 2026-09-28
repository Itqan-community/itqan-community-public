<?php

// Run: php packages/itqan-llms/tests/markdown_route_test.php
// Exits non-zero on the first failed assertion. No Flarum boot, no database.
//
// Two things are pinned here:
//
//  1. A route cannot serve `/d/{id-slug}.md`. Core's discussion route is
//     `/d/{id:\d+(?:-[^/]*)?}` and `[^/]*` matches dots, so it claims
//     `/d/123-my-title.md` with id `123-my-title.md`. Verified below with the
//     same FastRoute dispatcher Flarum's router uses. That is why the Markdown
//     URL is handled by middleware ahead of the router instead.
//  2. The middleware only claims genuine Markdown URLs and leaves every other
//     path to the router, including the ones core handles.

error_reporting(E_ALL & ~E_DEPRECATED);
require __DIR__.'/../../../vendor/autoload.php';

use FastRoute\Dispatcher;

use Flarum\Http\RouteCollection;
use Itqan\Llms\Controller\MarkdownDiscussionController;
use Itqan\Llms\Middleware\MarkdownDiscussionMiddleware;
use Laminas\Diactoros\Response\TextResponse;
use Laminas\Diactoros\ServerRequest;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Records the id the middleware handed on, and reports whether the request
 * reached the router instead.
 */
class SpyController extends MarkdownDiscussionController
{
    public string $seenId = '';

    public function __construct()
    {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $this->seenId = (string) ($request->getQueryParams()['id'] ?? '');

        return new TextResponse('markdown');
    }
}

class SpyHandler implements RequestHandlerInterface
{
    public bool $called = false;

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $this->called = true;

        return new TextResponse('router');
    }
}

$failures = 0;

function check(string $name, $expected, $actual): void
{
    global $failures;

    if ($expected === $actual) {
        echo "PASS $name\n";

        return;
    }

    $failures++;
    echo "FAIL $name\n";
    echo '  expected: '.var_export($expected, true)."\n";
    echo '  actual:   '.var_export($actual, true)."\n";
}

// --- a route cannot do this job ------------------------------------------------

echo "-- why a route is not enough --\n";

$collection = new RouteCollection;

// Core registers its discussion route first, always.
$collection->get('/d/{id:\d+(?:-[^/]*)?}[/{near:[^/]*}]', 'discussion', 'core.Discussion');
$collection->get('/d/{id:[^/.]+}.md', 'discussion.markdown', 'markdown');

$dispatcher = new Dispatcher\GroupCountBased($collection->getRouteData());

$info = $dispatcher->dispatch('GET', '/d/123-my-title.md');

check(
    'core_route_swallows_the_md_url',
    'discussion',
    $info[0] === Dispatcher::NOT_FOUND ? null : $info[1]['name']
);
check('core_route_receives_a_bogus_id', '123-my-title.md', $info[2]['id'] ?? null);

echo "\n-- what the middleware claims --\n";

// --- the middleware's own behaviour -------------------------------------------

$claimed = [
    '/d/123.md'                => '123',
    '/d/123-my-title.md'       => '123-my-title',
    '/forum/d/123-my-title.md' => '123-my-title',
    '/d/123-99.md'             => '123-99',
    // An ordinary slug for a discussion titled "mdx": a dot is the only
    // character the slug may not contain.
    '/d/123-mdx.md'            => '123-mdx',
];

foreach ($claimed as $path => $expectedId) {
    $controller = new SpyController;
    $handler = new SpyHandler;

    (new MarkdownDiscussionMiddleware($controller))->process(
        new ServerRequest([], [], 'http://localhost'.$path, 'GET'),
        $handler
    );

    check("claims $path", true, ! $handler->called && $controller->seenId === $expectedId);
}

echo "\n-- what the middleware leaves alone --\n";

// Core's discussion route does not match `/d/123.5` or `/d/123.md/` either, so
// these genuinely 404 today. Claiming them would change the site's behaviour.
$untouched = [
    '/d/123',
    '/d/123-my-title',
    '/d/123/5',
    '/d/123.5',
    '/d/abc.md',
    '/d/123.md/',
    '/d/12.3.4.md',
    '/llms.txt',
    '/',
];

foreach ($untouched as $path) {
    $controller = new SpyController;
    $handler = new SpyHandler;

    (new MarkdownDiscussionMiddleware($controller))->process(
        new ServerRequest([], [], 'http://localhost'.$path, 'GET'),
        $handler
    );

    check("passes through $path", true, $handler->called);
}

echo "\n-- a query string cannot override the path --\n";

$controller = new SpyController;

(new MarkdownDiscussionMiddleware($controller))->process(
    (new ServerRequest([], [], 'http://localhost/d/7-real.md', 'GET'))->withQueryParams(['id' => '999']),
    new SpyHandler
);

check('path_wins_over_query', '7-real', $controller->seenId);

echo "\n-- ordering inside the forum middleware stack --\n";

$container = new Illuminate\Container\Container;

$container->instance('flarum.forum.middleware', [
    Flarum\Http\Middleware\InjectActorReference::class,
    'flarum.forum.error_handler',
    Flarum\Http\Middleware\StartSession::class,
    'flarum.forum.route_resolver',
    Flarum\Http\Middleware\CheckCsrfToken::class,
]);

(new Flarum\Extend\Middleware('forum'))
    ->insertBefore('flarum.forum.route_resolver', MarkdownDiscussionMiddleware::class)
    ->extend($container);

$stack = $container->make('flarum.forum.middleware');

$mine = array_search(MarkdownDiscussionMiddleware::class, $stack, true);
$router = array_search('flarum.forum.route_resolver', $stack, true);

check('runs_before_route_resolution', true, $mine !== false && $router !== false && $mine < $router);

// The controller asks the request who is asking, so the actor has to be on the
// request by the time the Markdown path is handled.
check(
    'runs_after_actor_injection',
    true,
    $mine > array_search(Flarum\Http\Middleware\InjectActorReference::class, $stack, true)
);

echo $failures === 0 ? "\nALL PASS\n" : "\n$failures FAILED\n";

exit($failures === 0 ? 0 : 1);
