<?php

// Run: php packages/itqan-llms/tests/conditional_request_test.php
// Exits non-zero on the first failed assertion.
//
// The controller advertised an ETag but never read If-None-Match, so the 304 the
// README promised could not happen. This drives the real controller with real
// Post models over in-memory sqlite, so the header handling and the validator
// contents are both exercised.

error_reporting(E_ALL & ~E_DEPRECATED);
require __DIR__.'/../../../vendor/autoload.php';

use Flarum\Discussion\Discussion;
use Flarum\Discussion\DiscussionRepository;
use Flarum\Formatter\Formatter;
use Flarum\Http\RequestUtil;
use Flarum\Post\CommentPost;
use Flarum\User\User;
use Illuminate\Cache\ArrayStore;
use Illuminate\Cache\Repository as CacheRepository;
use Illuminate\Container\Container;
use Illuminate\Database\Capsule\Manager as DB;
use Illuminate\Database\Schema\Blueprint;
use Itqan\Llms\Controller\MarkdownDiscussionController;
use Itqan\Llms\Markdown\DiscussionRenderer;
use Itqan\Llms\Markdown\HtmlToMarkdown;
use Itqan\Llms\Markdown\ThreadTree;
use Itqan\Llms\Support\ForumUrls;
use Laminas\Diactoros\ServerRequest;
use s9e\TextFormatter\Configurator;

/** Stands in for the actorReference that InjectActorReference puts on a request. */
class ActorReference
{
    private $actor;

    public function __construct($actor)
    {
        $this->actor = $actor;
    }

    public function getActor(): Flarum\User\User
    {
        return $this->actor;
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

// --- a container just rich enough ---------------------------------------------

$container = new Container;
Container::setInstance($container);

$cache = new CacheRepository(new ArrayStore);
$formatterDir = sys_get_temp_dir().'/itqan-llms-conditional';

if (! is_dir($formatterDir)) {
    mkdir($formatterDir, 0777, true);
}

$configurator = new Configurator;
$configurator->rendering->setEngine('PHP');
$configurator->rendering->getEngine()->cacheDir = $formatterDir;
$configurator->Litedown;
$cache->forever('flarum.formatter', $configurator->finalize());

CommentPost::setFormatter(new Formatter($cache, $formatterDir));

spl_autoload_register(function (string $class) use ($formatterDir): void {
    if (file_exists($formatterDir.'/'.$class.'.php')) {
        include $formatterDir.'/'.$class.'.php';
    }
});

(new ReflectionProperty(User::class, 'displayNameDriver'))
    ->setValue(null, new Flarum\User\DisplayName\UsernameDriver);

(new ReflectionProperty(User::class, 'gate'))->setValue(null, new class($container) extends Flarum\User\Access\Gate {
    public function __construct($container)
    {
        parent::__construct($container, []);
    }

    public function allows(User $actor, string $ability, $model): bool
    {
        return false;
    }
});

// --- a real discussion, so findOrFail and the post query actually run ----------

$db = new DB;
$db->addConnection(['driver' => 'sqlite', 'database' => ':memory:']);
$db->setAsGlobal();
$db->bootEloquent();

$schema = $db->getConnection()->getSchemaBuilder();

$schema->create('discussions', function (Blueprint $t) {
    $t->increments('id');
    $t->string('title');
    $t->string('slug')->nullable();
    $t->integer('comment_count')->default(0);
    $t->integer('votes')->default(0);
    $t->integer('is_private')->default(0);
    $t->integer('is_hidden')->default(0);
    $t->integer('is_locked')->default(0);
    $t->timestamp('created_at')->nullable();
    $t->timestamp('last_posted_at')->nullable();
});

$schema->create('posts', function (Blueprint $t) {
    $t->increments('id');
    $t->integer('discussion_id');
    $t->integer('number');
    $t->string('type')->default('comment');
    $t->text('content')->nullable();
    $t->integer('user_id')->nullable();
    $t->integer('votes')->default(0);
    $t->integer('reply_count')->default(0);
    $t->integer('parent_id')->nullable();
    $t->integer('is_private')->default(0);
    $t->timestamp('created_at')->nullable();
    $t->timestamp('edited_at')->nullable();
    $t->timestamp('hidden_at')->nullable();
});

$schema->create('posts_relations', function (Blueprint $t) {
    $t->integer('post_id');
    $t->integer('related_id');
    $t->string('type');
});

$schema->create('users', function (Blueprint $t) {
    $t->increments('id');
    $t->string('username');
    $t->string('display_name')->nullable();
    $t->integer('is_email_hidden')->default(0);
});

$schema->create('groups', function (Blueprint $t) {
    $t->increments('id');
    $t->string('name');
    $t->text('permissions')->nullable();
});

$schema->create('group_user', function (Blueprint $t) {
    $t->integer('user_id');
    $t->integer('group_id');
});

$schema->create('group_permission', function (Blueprint $t) {
    $t->integer('group_id');
    $t->string('permission');
});

DB::table('users')->insert(['id' => 1, 'username' => 'amina', 'display_name' => 'Amina']);
DB::table('discussions')->insert([
    'id' => 1,
    'title' => 'Conditional requests',
    'slug' => 'conditional-requests',
    'comment_count' => 2,
    'votes' => 4,
    'created_at' => '2026-01-01 10:00:00',
    'last_posted_at' => '2026-01-02 12:00:00',
]);

$repository = new DiscussionRepository;

$actor = new User;
$actor->setRawAttributes(['id' => 1, 'username' => 'amina']);
$actor->exists = true;

$urls = new class extends ForumUrls {
    public function __construct()
    {
    }

    public function toDiscussion(Flarum\Discussion\Discussion $discussion): string
    {
        return 'https://community.test/d/'.$discussion->id;
    }
};

$controller = new MarkdownDiscussionController(
    $repository,
    new DiscussionRenderer(new HtmlToMarkdown, new ThreadTree, $urls),
    // No tags or discussion-language here, which is the point: the optional
    // relations must be skipped rather than requested.
    new class implements Flarum\Settings\SettingsRepositoryInterface {
        public function get($key, $default = null)
        {
            return $key === 'extensions_enabled' ? '[]' : $default;
        }

        public function set($key, $value = null)
        {
        }

        public function all(): array
        {
            return ['extensions_enabled' => []];
        }

        public function delete($key)
        {
        }
    }
);

function request(array $headers = [], ?string $id = '1'): ServerRequest
{
    $actor = new Flarum\User\User;
    $actor->setRawAttributes(['id' => 1, 'username' => 'amina']);
    $actor->exists = true;

    $request = (new ServerRequest([], [], 'https://community.test/d/'.$id.'.md', 'GET'))
        ->withQueryParams(['id' => $id])
        ->withAttribute('actorReference', new ActorReference($actor));

    foreach ($headers as $name => $value) {
        $request = $request->withHeader($name, $value);
    }

    return $request;
}

// --- the first request --------------------------------------------------------

$first = $controller->handle(request());
$etag = $first->getHeaderLine('ETag');

check('first_request_is_200', 200, $first->getStatusCode());
check('etag_is_present', true, $etag !== '');
check('last_modified_is_present', true, $first->getHeaderLine('Last-Modified') !== '');
check('body_is_markdown', 'text/markdown; charset=utf-8', $first->getHeaderLine('Content-Type'));
check('not_publicly_cacheable', false, str_contains($first->getHeaderLine('Cache-Control'), 'public'));

// --- revalidation -------------------------------------------------------------

$second = $controller->handle(request(['If-None-Match' => $etag]));
check('matching_etag_is_304', 304, $second->getStatusCode());
check('304_has_no_body', '', (string) $second->getBody());
check('304_keeps_the_etag', $etag, $second->getHeaderLine('ETag'));

check('star_is_304', 304, $controller->handle(request(['If-None-Match' => '*']))->getStatusCode());
check(
    'weak_validator_is_304',
    304,
    $controller->handle(request(['If-None-Match' => 'W/'.$etag]))->getStatusCode()
);
check(
    'etag_among_several_is_304',
    304,
    $controller->handle(request(['If-None-Match' => '"other", '.$etag]))->getStatusCode()
);
check(
    'different_etag_is_200',
    200,
    $controller->handle(request(['If-None-Match' => '"nope"']))->getStatusCode()
);

$lastModified = $first->getHeaderLine('Last-Modified');

check(
    'if_modified_since_is_304',
    304,
    $controller->handle(request(['If-Modified-Since' => $lastModified]))->getStatusCode()
);
check(
    'older_if_modified_since_is_200',
    200,
    $controller->handle(request(['If-Modified-Since' => 'Mon, 01 Jan 1990 00:00:00 GMT']))->getStatusCode()
);

// --- precedence: If-None-Match wins, If-Modified-Since is ignored --------------
//
// RFC 7232: when If-None-Match is present it is the only validator that counts.
// Falling back to the date after a failed tag match returned a 304 to a client
// whose ETag was stale — and since the ETag covers vote scores, that is how a
// client kept being served the old score after a vote.

check(
    'non_matching_etag_beats_a_fresh_date',
    200,
    $controller->handle(request([
        'If-None-Match' => '"stale-tag"',
        'If-Modified-Since' => $lastModified,
    ]))->getStatusCode()
);

check(
    'non_matching_etag_beats_a_future_date',
    200,
    $controller->handle(request([
        'If-None-Match' => '"stale-tag"',
        'If-Modified-Since' => 'Mon, 01 Jan 2090 00:00:00 GMT',
    ]))->getStatusCode()
);

check(
    'matching_etag_still_wins_over_the_date',
    304,
    $controller->handle(request([
        'If-None-Match' => $etag,
        'If-Modified-Since' => 'Mon, 01 Jan 1990 00:00:00 GMT',
    ]))->getStatusCode()
);

// The date is only consulted when no If-None-Match was sent at all.
check(
    'date_alone_still_works',
    304,
    $controller->handle(request(['If-Modified-Since' => $lastModified]))->getStatusCode()
);

// --- a vote change must invalidate -------------------------------------------
//
// The Markdown states every score, so a score change changes the body even
// though no post was edited. A validator over timestamps alone would keep
// serving a stale score to a crawler indefinitely.

DB::table('discussions')->where('id', 1)->update(['votes' => 99]);

$afterVote = $controller->handle(request(['If-None-Match' => $etag]));

check('vote_change_busts_the_etag', 200, $afterVote->getStatusCode());
check('vote_change_makes_a_new_etag', true, $afterVote->getHeaderLine('ETag') !== $etag);
check('new_score_is_in_the_body', true, str_contains((string) $afterVote->getBody(), '+99'));

// --- a missing discussion is a 404, but a broken database is not --------------

$missing = $controller->handle(request([], '999'));

check('missing_discussion_is_404', 404, $missing->getStatusCode());
check('404_is_markdown', 'text/markdown; charset=utf-8', $missing->getHeaderLine('Content-Type'));

echo $failures === 0 ? "\nALL PASS\n" : "\n$failures FAILED\n";

exit($failures === 0 ? 0 : 1);
