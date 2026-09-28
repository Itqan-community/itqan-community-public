<?php

// Run: php packages/itqan-llms/tests/forum_urls_test.php
// Exits non-zero on the first failed assertion.
//
// URL building is where a mistyped method hides best. `ForumUrls` was once typed
// against RouteCollectionUrlGenerator, which has no `to()` method, and called a
// `baseUrl()` that does not exist — neither is visible to `php -l`, and both
// only surface when a crawler requests a .md URL on a live forum. This exercises
// the real generator and the real slug drivers.

error_reporting(E_ALL & ~E_DEPRECATED);
require __DIR__.'/../../../vendor/autoload.php';

use Flarum\Discussion\Discussion;
use Pipecraft\IdSlug\Discussion\IdSlugDriver;
use Flarum\Http\RouteCollection;
use Flarum\Http\RouteCollectionUrlGenerator;
use Flarum\Http\SlugManager;
use Flarum\Http\UrlGenerator;
use Itqan\Llms\Support\ForumUrls;

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

function makeUrlGenerator(string $base = 'https://community.test'): UrlGenerator
{
    $routes = new RouteCollection;
    $routes->get('/d/{id:\d+(?:-[^/]*)?}[/{near:[^/]*}]', 'discussion', 'core.Discussion');

    $url = (new ReflectionClass(UrlGenerator::class))->newInstanceWithoutConstructor();

    (new ReflectionProperty(UrlGenerator::class, 'routes'))->setValue($url, [
        'forum' => new RouteCollectionUrlGenerator($base, $routes),
    ]);

    return $url;
}

function makeDiscussion(int $id, string $slug): Discussion
{
    $discussion = new Discussion;
    // setRawAttributes, because setTitleAttribute would reach for the slugger.
    $discussion->setRawAttributes(['id' => $id, 'slug' => $slug]);
    $discussion->exists = true;

    return $discussion;
}

$discussion = makeDiscussion(42, 'best-way-to-learn-php-8');

// Flarum's SlugManager holds resolved driver instances, keyed by resource.
$stubRepository = new class extends Flarum\Discussion\DiscussionRepository {
    public function __construct()
    {
    }

    public function findOrFail($id, ?Flarum\User\User $user = null)
    {
        return makeDiscussion((int) $id, 'best-way-to-learn-php-8');
    }
};

$transliterated = new Flarum\Discussion\IdWithTransliteratedSlugDriver($stubRepository);

// --- the default driver: id-slug ----------------------------------------------

$default = new ForumUrls(
    makeUrlGenerator(),
    new SlugManager([Discussion::class => $transliterated])
);

check('default_slug_driver', 'https://community.test/d/42-best-way-to-learn-php-8', $default->toDiscussion($discussion));
check('default_markdown_url', 'https://community.test/d/42-best-way-to-learn-php-8.md', $default->toDiscussionMarkdown($discussion));
check('llms_txt_url', 'https://community.test/llms.txt', $default->toLlmsTxt());

// --- pipecraft's id-only driver ------------------------------------------------

// With this selected in the admin panel, `/d/42-slug` is a URL the site never
// generates, and the page's own canonical link says `/d/42`. Handing a crawler
// a different URL is what this check exists to prevent.
$idOnlyDriver = new IdSlugDriver($stubRepository);

$idOnly = new ForumUrls(
    makeUrlGenerator(),
    new SlugManager([Discussion::class => $idOnlyDriver])
);

check('id_only_driver_slug', 'https://community.test/d/42', $idOnly->toDiscussion($discussion));
check('id_only_driver_markdown', 'https://community.test/d/42.md', $idOnly->toDiscussionMarkdown($discussion));

// --- a forum in a subdirectory -------------------------------------------------

$sub = new ForumUrls(
    makeUrlGenerator('https://community.test/forum'),
    new SlugManager([Discussion::class => $transliterated])
);

check('subdirectory_discussion', 'https://community.test/forum/d/42-best-way-to-learn-php-8', $sub->toDiscussion($discussion));
check('subdirectory_llms_txt', 'https://community.test/forum/llms.txt', $sub->toLlmsTxt());

echo $failures === 0 ? "\nALL PASS\n" : "\n$failures FAILED\n";

exit($failures === 0 ? 0 : 1);
