<?php

// Run: php packages/itqan-llms/tests/container_wiring_test.php
// Exits non-zero on the first failed resolution.
//
// A wrong constructor signature does not show up in `php -l`, and the app only
// reaches it when a crawler requests a .md URL. Every class the extension
// registers is resolved here through the real service provider, against a
// container holding stand-ins for the bindings Flarum would supply.

error_reporting(E_ALL & ~E_DEPRECATED);
require __DIR__.'/../../../vendor/autoload.php';

use Flarum\Http\SlugManager;
use Flarum\Http\UrlGenerator;
use Illuminate\Container\Container;
use Itqan\Llms\ServiceProvider\LlmServiceProvider;

$container = new Container;
Container::setInstance($container);

// The real UrlGenerator and a real route collection, with the collection map
// injected directly so no Application has to boot. Binding the wrong class here
// is exactly the mistake this test is meant to catch: ForumUrls needs
// UrlGenerator, not RouteCollectionUrlGenerator.
$routes = new Flarum\Http\RouteCollection;
$routes->get('/d/{id:\d+(?:-[^/]*)?}[/{near:[^/]*}]', 'discussion', 'core.Discussion');
$routes->get('/llms.txt', 'llms.txt', 'llms.txt');

$url = (new ReflectionClass(UrlGenerator::class))->newInstanceWithoutConstructor();

(new ReflectionProperty(UrlGenerator::class, 'routes'))->setValue($url, [
    'forum' => new Flarum\Http\RouteCollectionUrlGenerator('https://community.test', $routes),
]);

$container->instance(UrlGenerator::class, $url);
$container->instance('flarum.http.url_generator', $url);
$container->instance(Flarum\Http\RouteCollectionUrlGenerator::class, $url);

$container->instance(SlugManager::class, new SlugManager([
    Flarum\Discussion\Discussion::class => new Flarum\Discussion\IdWithTransliteratedSlugDriver(
        new class extends Flarum\Discussion\DiscussionRepository {
            public function __construct()
            {
            }

            public function findOrFail($id, ?Flarum\User\User $user = null)
            {
                throw new RuntimeException('not used in this test');
            }
        }
    ),
]));

$container->instance(Flarum\Settings\SettingsRepositoryInterface::class, new class implements Flarum\Settings\SettingsRepositoryInterface {
    public function get($key, $default = null)
    {
        return $default;
    }

    public function set($key, $value = null)
    {
    }

    public function all(): array
    {
        return [];
    }

    public function delete($key)
    {
    }
});

(new LlmServiceProvider($container))->register();

$targets = [
    Itqan\Llms\Markdown\HtmlToMarkdown::class,
    Itqan\Llms\Markdown\ThreadTree::class,
    Itqan\Llms\Support\ForumUrls::class,
    Itqan\Llms\Markdown\DiscussionRenderer::class,
    Itqan\Llms\Controller\MarkdownDiscussionController::class,
    Itqan\Llms\Controller\LlmIndexController::class,
    Itqan\Llms\Content\AddAlternateLinks::class,
    Itqan\Llms\Middleware\MarkdownDiscussionMiddleware::class,
];

$failures = 0;

foreach ($targets as $target) {
    try {
        $container->make($target);
        echo "PASS resolves ".class_basename($target)."\n";
    } catch (\Throwable $e) {
        $failures++;
        echo "FAIL resolves ".class_basename($target).': '.$e->getMessage()."\n";
    }
}

// The shared services must be singletons, or every request rebuilds them.
$html = $container->make(Itqan\Llms\Markdown\HtmlToMarkdown::class);
check_same($html, $container->make(Itqan\Llms\Markdown\HtmlToMarkdown::class));

function check_same($a, $b): void
{
    global $failures;

    if ($a === $b) {
        echo "PASS HtmlToMarkdown is a singleton\n";

        return;
    }

    $failures++;
    echo "FAIL HtmlToMarkdown is rebuilt on every make()\n";
}

echo $failures === 0 ? "\nALL PASS\n" : "\n$failures FAILED\n";

exit($failures === 0 ? 0 : 1);
