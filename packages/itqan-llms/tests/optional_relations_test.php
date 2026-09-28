<?php

// Run: php packages/itqan-llms/tests/optional_relations_test.php
// Exits non-zero on the first failed assertion.
//
// Two lessons are pinned here.
//
// First: the controller used to ask for `loadMissing(['user', 'tags', 'language'])`
// unconditionally. `tags` and `language` are added by flarum/tags and
// fof/discussion-language through Extend\Model(), so on a forum without them
// Eloquent throws `RelationNotFoundException` — a 500 on a public endpoint,
// while the README claimed the extension worked without them.
//
// Second: the guard that replaced it asked `method_exists($discussion, 'tags')`.
// That is also always false, because Extend\Model() does not add a method: it
// puts a callback in AbstractModel::$customRelations. So Tags and Language were
// never loaded even where the extensions were enabled. The guard now asks
// whether the extension is enabled, which is the same thing LlmIndexController
// does and the same thing the rest of the forum works from.

error_reporting(E_ALL & ~E_DEPRECATED);
require __DIR__.'/../../../vendor/autoload.php';

use Flarum\Discussion\Discussion;
use Flarum\Discussion\DiscussionRepository;
use Illuminate\Database\Capsule\Manager as DB;
use Illuminate\Database\Schema\Blueprint;
use Itqan\Llms\Controller\MarkdownDiscussionController;
use Itqan\Llms\Markdown\DiscussionRenderer;
use Itqan\Llms\Markdown\HtmlToMarkdown;
use Itqan\Llms\Markdown\ThreadTree;
use Itqan\Llms\Support\ForumUrls;

/** Minimal stand-in so the language relation has a related class. */
class DiscussionLanguageStub extends Flarum\Database\AbstractModel
{
    protected $table = 'discussion_languages';
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

$db = new DB;
$db->addConnection(['driver' => 'sqlite', 'database' => ':memory:']);
$db->setAsGlobal();
$db->bootEloquent();

$schema = $db->getConnection()->getSchemaBuilder();

$schema->create('discussions', function (Blueprint $t) {
    $t->increments('id');
    $t->string('title');
});

// `user` is a real relation, so it has to be able to load. Without the table it
// fails first with a QueryException and the relation check below is never
// reached.
$schema->create('users', function (Blueprint $t) {
    $t->increments('id');
    $t->string('username');
});

$schema->create('tags', function (Blueprint $t) {
    $t->increments('id');
    $t->string('name');
});

$schema->create('discussion_tag', function (Blueprint $t) {
    $t->integer('discussion_id');
    $t->integer('tag_id');
});

// fof/discussion-language's related model, so that relation can be loaded
// rather than throwing on a missing table.
$schema->create('discussion_languages', function (Blueprint $t) {
    $t->increments('id');
    $t->string('name');
    $t->string('code')->nullable();
});

// The real Discussion class with no extenders run, i.e. neither flarum/tags nor
// fof/discussion-language enabled.
$discussion = new Discussion;
$discussion->setRawAttributes(['id' => 1, 'title' => 'A discussion']);
$discussion->exists = true;

// --- the two ways of asking whether a relation exists -------------------------

check('user_relation_is_a_method', true, method_exists($discussion, 'user'));
check('tags_is_not_a_method', false, method_exists($discussion, 'tags'));
check('language_is_not_a_method', false, method_exists($discussion, 'language'));

// Which is why method_exists is the wrong question, and why the first guard
// silently dropped Tags and Language on every forum.
check(
    'custom_relations_start_empty',
    [],
    array_filter(
        array_keys(Flarum\Database\AbstractModel::$customRelations),
        fn ($k) => str_contains($k, 'Discussion')
    )
);

// --- the bug ------------------------------------------------------------------

$boom = null;

try {
    $discussion->loadMissing(['user', 'tags', 'language']);
} catch (\Throwable $e) {
    $boom = get_class($e);
}

check('loadMissing_with_absent_relations_throws', 'Illuminate\Database\Eloquent\RelationNotFoundException', $boom);

// --- the fix, through the controller's own method -----------------------------

$urls = new class extends ForumUrls {
    public function __construct()
    {
    }

    public function toDiscussion(Flarum\Discussion\Discussion $d): string
    {
        return 'https://community.test/d/'.$d->id;
    }
};

/**
 * @param string[] $enabled extension ids, as flarum's settings store them
 */
function controllerFor(array $enabled): MarkdownDiscussionController
{
    $settings = new class($enabled) implements Flarum\Settings\SettingsRepositoryInterface {
        private $enabled;

        public function __construct(array $enabled)
        {
            $this->enabled = $enabled;
        }

        public function get($key, $default = null)
        {
            return $key === 'extensions_enabled' ? json_encode($this->enabled) : $default;
        }

        public function set($key, $value = null)
        {
        }

        public function all(): array
        {
            return ['extensions_enabled' => $this->enabled];
        }

        public function delete($key)
        {
        }
    };

    $renderer = new DiscussionRenderer(
        new HtmlToMarkdown,
        new ThreadTree,
        new class extends ForumUrls {
            public function __construct()
            {
            }

            public function toDiscussion(Flarum\Discussion\Discussion $d): string
            {
                return 'https://community.test/d/'.$d->id;
            }
        }
    );

    $controller = new MarkdownDiscussionController(new DiscussionRepository, $renderer, $settings);

    // The real controller reads the settings; this replaces the binding it
    // resolves, so the enabled check runs for real.
    $property = new ReflectionProperty($controller, 'settings');
    $property->setAccessible(true);
    $property->setValue($controller, $settings);

    return $controller;
}

function loadRelations(MarkdownDiscussionController $controller, Discussion $discussion): ?string
{
    $method = (new ReflectionClass($controller))->getMethod('loadOptionalRelations');
    $method->setAccessible(true);

    try {
        $method->invoke($controller, $discussion);

        return null;
    } catch (\Throwable $e) {
        return get_class($e);
    }
}

// Neither extension enabled: must not throw, and must not touch tags.
$fresh = new Discussion;
$fresh->setRawAttributes(['id' => 2, 'title' => 'B']);
$fresh->exists = true;

check('no_tags_enabled_does_not_throw', null, loadRelations(controllerFor([]), $fresh));
check('no_tags_enabled_loads_user', true, $fresh->relationLoaded('user'));
check('no_tags_enabled_skips_tags', false, $fresh->relationLoaded('tags'));
check('no_tags_enabled_skips_language', false, $fresh->relationLoaded('language'));

// The regression this exists for: with flarum/tags enabled, the relation is
// defined by Extend\Model(), not as a method, and it must be loaded.
$withTags = new Discussion;
$withTags->setRawAttributes(['id' => 3, 'title' => 'C']);
$withTags->exists = true;

// Stand in for flarum/tags' extender, which is a callback in customRelations.
Flarum\Database\AbstractModel::$customRelations[Discussion::class.'.tags'] = function ($model) {
    return $model->belongsToMany(Flarum\Tags\Tag::class, 'discussion_tag');
};

$threw = loadRelations(controllerFor(['flarum-tags']), $withTags);

check('tags_enabled_does_not_throw', null, $threw);
check('tags_enabled_loads_tags', true, $withTags->relationLoaded('tags'));

// And fof/discussion-language, the other relation that was being dropped.
$withLanguage = new Discussion;
$withLanguage->setRawAttributes(['id' => 4, 'title' => 'D']);
$withLanguage->exists = true;

Flarum\Database\AbstractModel::$customRelations[Discussion::class.'.language'] = function ($model) {
    return $model->hasOne(DiscussionLanguageStub::class, 'id', 'language_id');
};

$class = new ReflectionClass(Discussion::class);
$languageThrew = loadRelations(controllerFor(['fof-discussion-language']), $withLanguage);

check('language_enabled_does_not_throw', null, $languageThrew);
check('language_enabled_loads_language', true, $withLanguage->relationLoaded('language'));

echo $failures === 0 ? "\nALL PASS\n" : "\n$failures FAILED\n";

exit($failures === 0 ? 0 : 1);

