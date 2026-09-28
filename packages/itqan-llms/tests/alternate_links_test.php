<?php

// Run: php packages/itqan-llms/tests/alternate_links_test.php
// Exits non-zero on the first failed assertion.
//
// The llms.txt spec pairs two relations. `alternate` points at the Markdown of
// this page; `describedby` points at the llms.txt that covers it. Emitting only
// the first leaves an agent that landed on a thread with no pointer to the
// index, which is what the reference implementation did.

error_reporting(E_ALL & ~E_DEPRECATED);
require __DIR__.'/../../../vendor/autoload.php';

use Flarum\Frontend\Document;
use Flarum\Http\RouteCollectionUrlGenerator;
use Flarum\Http\SlugManager;
use Itqan\Llms\Content\AddAlternateLinks;
use Itqan\Llms\Support\ForumUrls;
use Laminas\Diactoros\ServerRequest;

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

function checkContains(string $name, string $needle, string $haystack): void
{
    global $failures;

    if (str_contains($haystack, $needle)) {
        echo "PASS $name\n";

        return;
    }

    $failures++;
    echo "FAIL $name\n";
    echo '  expected to contain: '.var_export($needle, true)."\n";
    echo '  in:                  '.var_export($haystack, true)."\n";
}

// The real UrlGenerator and the real route collection, with the collection map
// injected directly. Building it the normal way needs a booted Application just
// to learn the base URL, and `to()` — the method under test — is untouched.
$routes = new Flarum\Http\RouteCollection;
$routes->get('/d/{id:\d+(?:-[^/]*)?}[/{near:[^/]*}]', 'discussion', 'core.Discussion');

$url = (new ReflectionClass(Flarum\Http\UrlGenerator::class))->newInstanceWithoutConstructor();

(new ReflectionProperty(Flarum\Http\UrlGenerator::class, 'routes'))->setValue($url, [
    'forum' => new Flarum\Http\RouteCollectionUrlGenerator('https://community.test', $routes),
]);

$add = new AddAlternateLinks(new ForumUrls($url, new SlugManager([])));

// Only `$head` and `$canonicalUrl` are read here, and both are public, so the
// constructor's view factory and preloaded API document are bypassed rather
// than stood up.
function makeDocument(): Document
{
    return (new ReflectionClass(Document::class))->newInstanceWithoutConstructor();
}

// --- on a discussion page -----------------------------------------------------

$document = makeDocument();
$document->canonicalUrl = 'https://community.test/d/1-best-way-to-learn-php-8';

$request = (new ServerRequest([], [], 'https://community.test/d/1-best-way-to-learn-php-8', 'GET'))
    ->withAttribute('routeName', 'discussion')
    ->withAttribute('routeParameters', ['id' => '1-best-way-to-learn-php-8']);

$add($document, $request);

$head = implode("\n", $document->head);

check('two_links_emitted', 2, count($document->head));
checkContains('alternate_relation', 'rel="alternate"', $head);
checkContains('alternate_type', 'type="text/markdown"', $head);
checkContains('describedby_relation', 'rel="describedby"', $head);
checkContains('points_at_markdown', 'https://community.test/d/1-best-way-to-learn-php-8.md', $head);
checkContains('points_at_index', 'https://community.test/llms.txt', $head);
check('no_trailing_slash_double_dot', false, str_contains($head, '/.md'));

// The URL comes from the route parameter, not from $document->canonicalUrl.
//
// Flarum populates the document by running content callbacks in registration
// order, and core's Discussion content — the thing that sets canonicalUrl — is
// registered lazily when the route handler runs, after every extender has
// registered its own. An extender registered at boot therefore never sees it.
// The old code branched on canonicalUrl, so that branch could not execute and
// the fallback re-queried the discussion on every discussion page view.
$document = makeDocument();
$document->canonicalUrl = 'https://community.test/forum/d/9';

$add(
    $document,
    (new ServerRequest([], [], 'https://community.test/forum/d/9', 'GET'))
        ->withAttribute('routeName', 'discussion')
        ->withAttribute('routeParameters', ['id' => '9'])
);

$head = implode("\n", $document->head);

checkContains('subdirectory_markdown_url', 'https://community.test/d/9.md', $head);

// Built from the route parameter, so a canonicalUrl that disagrees is ignored
// rather than half-used.
$document = makeDocument();
$document->canonicalUrl = 'https://community.test/some/other/place';

$add(
    $document,
    (new ServerRequest([], [], 'https://community.test/d/9', 'GET'))
        ->withAttribute('routeName', 'discussion')
        ->withAttribute('routeParameters', ['id' => '9-best-way'])
);

checkContains(
    'ignores_canonical_url',
    'https://community.test/d/9-best-way.md',
    implode("\n", $document->head)
);

// No database access: the previous fallback called Discussion::find() on every
// discussion page view. A slug that cannot exist still produces a URL.
$document = makeDocument();
$document->canonicalUrl = 'https://community.test/d/999999999';

$add(
    $document,
    (new ServerRequest([], [], 'https://community.test/d/999999999', 'GET'))
        ->withAttribute('routeName', 'discussion')
        ->withAttribute('routeParameters', ['id' => '999999999-does-not-exist'])
);

checkContains(
    'no_database_lookup',
    'https://community.test/d/999999999-does-not-exist.md',
    implode("\n", $document->head)
);

// --- on other pages -----------------------------------------------------------

foreach (['index', 'tag', 'user', 'api'] as $route) {
    $document = makeDocument();
    $document->canonicalUrl = 'https://community.test/whatever';

    $add(
        $document,
        (new ServerRequest([], [], 'https://community.test/whatever', 'GET'))
            ->withAttribute('routeName', $route)
            ->withAttribute('routeParameters', ['id' => '1'])
    );

    check("no links on $route", 0, count($document->head));
}

// A discussion route with no id, e.g. a default route, adds nothing rather than
// emitting a broken link.
$document = makeDocument();
$document->canonicalUrl = 'https://community.test/';

$add(
    $document,
    (new ServerRequest([], [], 'https://community.test/', 'GET'))
        ->withAttribute('routeName', 'discussion')
        ->withAttribute('routeParameters', [])
);

check('no id, no links', 0, count($document->head));

echo $failures === 0 ? "\nALL PASS\n" : "\n$failures FAILED\n";

exit($failures === 0 ? 0 : 1);
