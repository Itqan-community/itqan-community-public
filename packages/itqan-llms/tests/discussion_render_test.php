<?php

// Run: php packages/itqan-llms/tests/discussion_render_test.php
// Exits non-zero on the first failed assertion. No database.
//
// This is the end-to-end check that matters: a real Post goes through Flarum's
// own text formatter, and the rendered HTML comes back as Markdown that still
// contains the link targets, the code fences and the reply structure. The unit
// tests feed the converter HTML by hand; this one proves the HTML is what the
// formatter actually emits.

error_reporting(E_ALL & ~E_DEPRECATED);
require __DIR__.'/../../../vendor/autoload.php';

use Flarum\Discussion\Discussion;
use Flarum\Formatter\Formatter;
use Flarum\Post\CommentPost;
use Flarum\Post\Post;
use Flarum\User\User;
use Illuminate\Cache\ArrayStore;
use Illuminate\Container\Container;
use Illuminate\Contracts\Cache\Repository as CacheContract;
use Illuminate\Filesystem\Filesystem;
use Itqan\Llms\Markdown\DiscussionRenderer;
use Itqan\Llms\Markdown\HtmlToMarkdown;
use Itqan\Llms\Markdown\ThreadTree;
use Itqan\Llms\Support\ForumUrls;
use Laminas\Diactoros\ServerRequest;
use s9e\TextFormatter\Configurator;

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

// --- a container just rich enough for the formatter and the gate ---------------

$container = new Container;
Container::setInstance($container);

$cache = new Illuminate\Cache\Repository(new ArrayStore);
$container->instance(CacheContract::class, $cache);
$container->instance('cache', $cache);

$formatterCacheDir = sys_get_temp_dir().'/itqan-llms-formatter';

if (! is_dir($formatterCacheDir)) {
    mkdir($formatterCacheDir, 0777, true);
}

$formatter = new Formatter($cache, $formatterCacheDir);

// Flarum's own defaults plus the markdown plugin, so the HTML below is the
// HTML the formatter really produces. The PHP rendering engine is what Flarum
// selects; the XSLT default needs an extension this CLI does not have.
$configurator = new Configurator;
$configurator->rendering->setEngine('PHP');
$configurator->rendering->getEngine()->cacheDir = $formatterCacheDir;
$configurator->Litedown;
$cache->forever('flarum.formatter', $configurator->finalize());

CommentPost::setFormatter($formatter);

// Flarum::Formatter::getRenderer() registers this to pull in the renderer it
// generates on the fly. Without it, rendering dies on a missing class. The
// directory is captured explicitly: a closure does not see the enclosing
// file's variables otherwise.
spl_autoload_register(function (string $class) use ($formatterCacheDir): void {
    $file = $formatterCacheDir.'/'.$class.'.php';

    if (file_exists($file)) {
        include $file;
    }
});

// A gate that denies everything, which is what a guest gets. The renderer only
// consults it for hidden posts.
$gate = new class($container) extends Flarum\User\Access\Gate {
    public function __construct($container)
    {
        parent::__construct($container, []);
    }

    public function allows(User $actor, string $ability, $model): bool
    {
        return false;
    }
};

(new ReflectionProperty(User::class, 'gate'))->setValue(null, $gate);

// `display_name` is served by a driver resolved from the container.
(new ReflectionProperty(User::class, 'displayNameDriver'))
    ->setValue(null, new Flarum\User\DisplayName\UsernameDriver);

// A guest's permissions are answered from its group memberships, so an
// in-memory database stands in for the real one. The other package's test does
// the same.
$db = new Illuminate\Database\Capsule\Manager;
$db->addConnection(['driver' => 'sqlite', 'database' => ':memory:']);
$schema = $db->getConnection()->getSchemaBuilder();

$schema->create('groups', function (Illuminate\Database\Schema\Blueprint $t) {
    $t->increments('id');
    $t->string('name');
    $t->text('permissions')->nullable();
});

$schema->create('group_user', function (Illuminate\Database\Schema\Blueprint $t) {
    $t->integer('user_id');
    $t->integer('group_id');
});

$schema->create('group_permission', function (Illuminate\Database\Schema\Blueprint $t) {
    $t->integer('group_id');
    $t->string('permission');
});

$db->setAsGlobal();
$db->bootEloquent();

// --- models built without touching a database ----------------------------------

$request = new ServerRequest([], [], 'http://localhost/d/1', 'GET');

$author = new User;
$author->id = 10;
$author->username = 'amina';
$author->display_name = 'Amina';

$poster = new User;
$poster->id = 11;
$poster->username = 'yusuf';
$poster->display_name = 'Yusuf';

/**
 * setRawAttributes bypasses Discussion::setTitleAttribute, which would reach
 * for the slug generator in the container.
 */
function makeDiscussion(array $attributes): Discussion
{
    $discussion = new Discussion;
    $discussion->setRawAttributes($attributes + [
        'comment_count' => 0,
        'votes' => 0,
    ]);
    $discussion->exists = true;

    return $discussion;
}

function makePost(int $id, int $number, ?int $parentId, User $user, string $source, array $extra = []): Post
{
    $createdAt = new DateTimeImmutable('2026-01-0'.min($id, 9).'T08:30:00+00:00');

    $post = new CommentPost;
    $post->setRawAttributes($extra + [
        'id' => $id,
        'number' => $number,
        'discussion_id' => 1,
        'user_id' => $user->id,
        'parent_id' => $parentId,
        'reply_count' => 0,
        'votes' => 0,
        'hidden_at' => null,
        'edited_at' => null,
        'created_at' => $createdAt,
    ]);
    $post->exists = true;

    // Parsed on save, exactly as CommentPost::setContentAttribute does.
    $post->setContentAttribute($source, $user);

    $post->setRelation('user', $user);

    return $post;
}

$discussion = makeDiscussion([
    'id' => 1,
    'title' => 'Best way to learn PHP 8?',
    'created_at' => new DateTimeImmutable('2026-01-01T10:00:00+00:00'),
    'last_posted_at' => new DateTimeImmutable('2026-01-02T12:00:00+00:00'),
    'votes' => 12,
    'user_id' => $author->id,
]);

$discussion->setRelation('user', $author);

$opening = makePost(1, 1, null, $author, <<<'MD'
I keep coming back to **PHP 8**. Where should I start?

The manual is here: https://www.php.net/manual/en/
MD);

$reply = makePost(2, 2, null, $author, 'Start with the [migration guide](https://www.php.net/migration/overview.php).', ['votes' => 5]);

$nested = makePost(3, 3, 2, $poster, 'Thanks — does it cover enums?', ['votes' => 2, 'reply_count' => 1]);

$deep = makePost(4, 4, 3, $author, 'Yes, [enums](https://www.php.net/manual/en/language.enumerations.overview.php) are chapter 12.', ['votes' => 1]);

$unrelated = makePost(5, 5, null, $poster, 'Unrelated: has anyone tried PHPStan?', ['votes' => 3]);

$hidden = makePost(6, 6, null, $poster, 'Something a moderator removed.', [
    'hidden_at' => new DateTimeImmutable('2026-01-03T09:00:00+00:00'),
]);

$withCode = makePost(7, 7, null, $author, <<<'MD'
Example:

```php
enum Suit {
    case Hearts;
}
```

And a list:

- one
- two
MD);

// --- render --------------------------------------------------------------------

$urls = new class extends ForumUrls {
    public function __construct()
    {
    }

    public function toDiscussion(Flarum\Discussion\Discussion $discussion): string
    {
        return 'https://community.test/d/'.$discussion->id.'-'.$discussion->title;
    }
};

$renderer = new DiscussionRenderer(new HtmlToMarkdown, new ThreadTree, $urls);

$guest = new User;

$markdown = $renderer->render(
    $discussion,
    [$opening, $reply, $nested, $deep, $unrelated, $hidden, $withCode],
    $guest,
    $request
);

echo "----- rendered Markdown -----\n";
echo $markdown;
echo "\n------------------------------\n\n";

// --- the header ----------------------------------------------------------------

// `^# ` at the start of a line matches the H1 but not the `## Post` headings,
// and the H1 is the very first thing in the document.
check('starts_with_a_single_h1', 1, preg_match_all('/^# /m', $markdown));
check('h1_is_on_the_first_line', 1, preg_match('/\A# /', $markdown));
checkContains('h1_is_the_title', '# Best way to learn PHP 8?', $markdown);
checkContains('discussion_id', '- **Discussion ID**: 1', $markdown);
checkContains('discussion_score', '- **Score**: +12', $markdown);
checkContains('author', '- **Author**: amina (@amina)', $markdown);

// --- the reply tree ------------------------------------------------------------

// The ordering assertion: post #4 answers #3, which answers #2, and both were
// written after unrelated post #5. They must appear in conversation order.
$order = [];

foreach (explode("\n", $markdown) as $line) {
    if (preg_match('/^## Post #(\d+)/', $line, $m)) {
        $order[] = (int) $m[1];
    }
}

check('posts_come_out_in_conversation_order', [1, 2, 3, 4, 5, 6, 7], $order);
checkContains('reply_names_its_parent', '- **In reply to**: post #2', $markdown);
checkContains('nested_reply_names_its_parent', '- **In reply to**: post #3', $markdown);
checkContains('reply_depth_reported', '- **Reply depth**: 1', $markdown);
checkContains('deepest_reply_depth', '- **Reply depth**: 2', $markdown);
checkContains('reply_count_surfaced', '- **Replies to this comment**: 1', $markdown);

// --- per-post scores -----------------------------------------------------------

checkContains('post_score_surfaced', '- **Score**: +5', $markdown);
checkContains('negative_score_signed', '- **Score**: +12', $markdown);

// --- the content survived as Markdown, not flat text --------------------------

checkContains('link_target_from_formatter', 'https://www.php.net/migration/overview.php', $markdown);
checkContains('manual_link', 'https://www.php.net/manual/en/', $markdown);
checkContains('nested_link', 'https://www.php.net/manual/en/language.enumerations.overview.php', $markdown);
checkContains('strong_preserved', '**PHP 8**', $markdown);
checkContains('code_fence_preserved', '```php', $markdown);
checkContains('enum_in_code_block', 'case Hearts;', $markdown);
checkContains('list_preserved', "- one\n- two", $markdown);

// Every post is present, including the one nobody can see.
checkContains('hidden_post_marked', 'This comment was hidden by a moderator', $markdown);
checkNotContains('hidden_post_text_withheld', 'Something a moderator removed', $markdown);
checkContains('hidden_post_placeholder', '[This comment was removed]', $markdown);

echo $failures === 0 ? "\nALL PASS\n" : "\n$failures FAILED\n";

exit($failures === 0 ? 0 : 1);
