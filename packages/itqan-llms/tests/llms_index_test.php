<?php

// Run: php packages/itqan-llms/tests/llms_index_test.php
// Exits non-zero on the first failed assertion. No database.
//
// Checks the shape https://llmstxt.org asks for, in order:
//
//   - one H1 with the site name, the only required section
//   - a blockquote with the short summary
//   - optional prose containing no headings
//   - zero or more H2 sections, each a list of - [name](url): notes links
//
// It also pins the two things the reference implementation got wrong here: the
// site description is stored as HTML and has to be flattened, and the listing
// must not carry a `---` rule or a "generated on" footer, which a parser would
// read as an unnamed section.

error_reporting(E_ALL & ~E_DEPRECATED);
require __DIR__.'/../../../vendor/autoload.php';

use Flarum\Discussion\Discussion;
use Flarum\Http\RouteCollectionUrlGenerator;
use Flarum\Http\SlugManager;
use Flarum\Settings\SettingsRepositoryInterface;
use Itqan\Llms\Controller\LlmIndexController;
use Itqan\Llms\Support\ForumUrls;
use Laminas\Diactoros\ServerRequest;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Server\RequestHandlerInterface;

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

function checkNotContains(string $name, string $needle, string $haystack): void
{
    global $failures;

    if (! str_contains($haystack, $needle)) {
        echo "PASS $name\n";

        return;
    }

    $failures++;
    echo "FAIL $name\n";
    echo '  expected NOT to contain: '.var_export($needle, true)."\n";
    echo '  in:                      '.var_export($haystack, true)."\n";
}

// --- stand-ins for the container bindings the controller asks for --------------

$settings = new class implements SettingsRepositoryInterface {
    public array $values = [
        'extensions_enabled' => ['flarum-tags', 'flarum-markdown'],
        'forum_title' => 'Itqan Community',
        // Stored as HTML, because that is what the admin panel produces.
        'forum_description' => "A community for <b>Muslims</b> in tech.\nSecond line.",
    ];

    public function get($key, $default = null)
    {
        return $this->values[$key] ?? $default;
    }

    public function set($key, $value = null)
    {
        $this->values[$key] = $value;
    }

    public function all(): array
    {
        return $this->values;
    }

    public function delete($key)
    {
        unset($this->values[$key]);
    }
};

$urls = new class extends ForumUrls {
    public function __construct()
    {
    }

    public function toDiscussionMarkdown(Flarum\Discussion\Discussion $discussion): string
    {
        return 'https://community.test/d/'.$discussion->id.'-'.self::slug($discussion->title).'.md';
    }

    public static function slug(string $title): string
    {
        $slug = strtolower(preg_replace('/[^A-Za-z0-9]+/', '-', $title) ?? $title);

        return trim($slug, '-');
    }
};

/**
 * `whereVisibleTo` normally needs a policy; the controller only needs the query
 * to be returned, so it is intercepted here.
 */
$repository = new class(Discussions::class) extends Flarum\Discussion\DiscussionRepository {
    public function __construct()
    {
    }

    public function query()
    {
        return Discussions::$query;
    }
};

class Discussions
{
    public static $query = null;
}

// `display_name` is served by a driver on the static User class.
(new ReflectionProperty(Flarum\User\User::class, 'displayNameDriver'))
    ->setValue(null, new Flarum\User\DisplayName\UsernameDriver);

$tag = new stdClass;
$tag->name = 'Programming';

$other = new stdClass;
$other->name = 'Career';

// Relations live here until the query eager loads them, so that forgetting
// `->with('tags')` in the controller actually shows up as a failure. The
// previous version of this test set the relations directly, which meant the
// tag grouping passed whether or not the controller asked for them — and the
// real forum, which does not get them for free, grouped nothing.
class DiscussionFixtures
{
    public static array $relations = [];
}

function makeDiscussion(int $id, string $title, int $votes, int $comments, ?object $tag): Discussion
{
    $discussion = new Discussion;
    $discussion->setRawAttributes([
        'id' => $id,
        'title' => $title,
        'votes' => $votes,
        'comment_count' => $comments,
        'last_posted_at' => new DateTimeImmutable('2026-01-05T09:00:00+00:00'),
    ]);
    $discussion->exists = true;

    $user = new Flarum\User\User;
    $user->username = 'amina';
    $user->setRawAttributes(['id' => 1, 'username' => 'amina']);

    DiscussionFixtures::$relations[$id] = [
        'user' => $user,
        'tags' => $tag ? [$tag] : [],
    ];

    return $discussion;
}

Discussions::$query = new class {
    public array $rows = [];

    /** Relations the controller asked to eager load. */
    public array $eager = [];

    public function whereVisibleTo($actor)
    {
        return $this;
    }

    public function with($relations)
    {
        $this->eager = array_merge($this->eager, (array) $relations);

        return $this;
    }

    public function orderBy($column, $direction = 'asc')
    {
        return $this;
    }

    public function limit($count)
    {
        return $this;
    }

    public function get()
    {
        return new Illuminate\Support\Collection(array_map(function (Discussion $discussion) {
            // Eager loading, as the database would: only the requested
            // relations are attached, so relationLoaded() is truthful.
            foreach ($this->eager as $relation) {
                $discussion->setRelation(
                    $relation,
                    DiscussionFixtures::$relations[(int) $discussion->id][$relation] ?? null
                );
            }

            return $discussion;
        }, $this->rows));
    }
};

Discussions::$query->rows = [
    makeDiscussion(1, 'Learning PHP 8', 12, 4, $tag),
    makeDiscussion(2, 'Finding a first job', 3, 9, $other),
    makeDiscussion(3, 'A very long title that keeps going and going and going to see wrapping', 0, 1, null),
];

$controller = new LlmIndexController($repository, $settings, $urls);

// RequestUtil::getActor() reads the actorReference that InjectActorReference
// puts on the request, so the stub has to provide one.
$request = (new ServerRequest([], [], 'http://localhost/llms.txt', 'GET'))
    ->withAttribute('actorReference', new class {
        public function getActor(): Flarum\User\User
        {
            return new Flarum\User\User;
        }
    });

$response = $controller->handle($request);
$body = (string) $response->getBody();

echo "----- llms.txt -----\n";
echo $body;
echo "--------------------\n\n";

// --- required structure -------------------------------------------------------

check('content_type', 'text/plain; charset=utf-8', $response->getHeaderLine('Content-Type'));
check('status', 200, $response->getStatusCode());
check('starts_with_h1', 1, preg_match('/\A# /', $body));
check('h1_uses_forum_title', 1, preg_match('/\A# Itqan Community\n/', $body));
check('exactly_one_h1', 1, preg_match_all('/^# /m', $body));
check('summary_blockquote', 1, preg_match('/^> A community for Muslims in tech\. Second line\.$/m', $body));

// The description is HTML in the database; a raw <b> or a newline would break
// the blockquote.
checkNotContains('html_stripped', '<b>', $body);
checkNotContains('no_newline_in_blockquote', "tech.\n> Second", $body);

// --- the file lists -----------------------------------------------------------

checkContains('section_heading', '## Programming', $body);
checkContains('link_is_markdown_url', '[Learning PHP 8](https://community.test/d/1-learning-php-8.md)', $body);
checkContains('link_has_notes', 'score 12', $body);
checkContains('comment_count_as_notes', '4 comments', $body);
checkContains('last_activity_as_notes', 'last active 2026-01-05T09:00:00+00:00', $body);
checkContains('untagged_bucket', '## Other discussions', $body);
checkContains('single_comment_singular', '1 comment', $body);

// Every link must point at a .md URL, or an agent following it lands on HTML.
// The `:` and its notes are optional, so the URL is captured either way.
preg_match_all('/^- \[[^\]]+\]\(([^)]+)\)(?::|$)/m', $body, $links);

check('links_found', true, count($links[1]) > 0);

foreach ($links[1] as $link) {
    check("link_is_markdown: $link", true, str_ends_with($link, '.md'));
}

// --- no non-standard trailing matter -----------------------------------------

// The reference implementation ended the file with a `---` rule and a
// "Generated by ... on <date>" line. A parser reads that as an unnamed section.
checkNotContains('no_trailing_rule', "\n---\n", $body);
checkNotContains('no_generated_footer', 'Generated by', $body);
check('ends_with_a_link_line', 1, preg_match('/\.md\)\: [^\n]*\n$/', $body));

// --- every H2 introduces a list, never prose ----------------------------------

preg_match_all('/^## (.+)$/m', $body, $headings);

foreach ($headings[1] as $index => $heading) {
    $offset = strpos($body, '## '.$heading);
    $rest = substr($body, $offset + strlen('## '.$heading) + 1);
    $firstLine = trim(strtok($rest, "\n"));

    check("section '$heading' starts with a link or a note", true, str_starts_with($firstLine, '- [') || str_starts_with($firstLine, '*'));
}

// --- more tag sections than fit before the fold --------------------------------

// Past PRIMARY_SECTIONS the rest move under `## Optional`, which the spec
// reserves for secondary information an agent may skip.
$many = [];

for ($i = 0; $i < 9; $i++) {
    $tag = new stdClass;
    $tag->name = 'Tag '.$i;
    $many[] = makeDiscussion(100 + $i, 'Discussion '.$i, $i, 1, $tag);
}

Discussions::$query->rows = $many;

$body = (string) $controller->handle($request)->getBody();

checkContains('optional_section_appears', "\n## Optional\n", $body);
check('optional_is_last_section', 1, preg_match('/\n## Optional\n(?:.|\n)*$/', $body));
checkContains('optional_explains_itself', 'can skip this section', $body);

// The overflow is one flat list, so an agent can tell where one tag's entries
// end and the next begins. Nested headings would break the file-list format.
check('optional_has_no_subheadings', 0, preg_match('/^### /m', $body));
check('optional_is_one_list', 1, preg_match('/\n## Optional\n+(?:(?!\n## )[\s\S])*?(?=\n## |$)/', $body));

// Per-actor content must not be stored in a shared cache: the index is built
// with whereVisibleTo($actor), so a public entry could serve a tag-gated
// discussion to a guest.
check('not_publicly_cacheable', true, ! str_contains($response->getHeaderLine('Cache-Control'), 'public'));
check('varies_on_cookie', true, str_contains($response->getHeaderLine('Vary'), 'Cookie'));

echo $failures === 0 ? "\nALL PASS\n" : "\n$failures FAILED\n";

exit($failures === 0 ? 0 : 1);
