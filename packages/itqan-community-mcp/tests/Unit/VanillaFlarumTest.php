<?php

declare(strict_types=1);

namespace Itqan\CommunityMcp\Tests\Unit;

use Flarum\Database\AbstractModel;
use Flarum\Discussion\Discussion;
use Flarum\User\Guest;
use Illuminate\Cache\ArrayStore;
use Illuminate\Cache\Repository as CacheRepository;
use Itqan\CommunityMcp\Http\Controller\LlmsTxtController;
use Itqan\CommunityMcp\Mcp\Resources\ForumOverviewResource;
use Itqan\CommunityMcp\Mcp\Tools\GetUserProfileAndPostsTool;
use Itqan\CommunityMcp\Mcp\Tools\ListTagsTool;
use Itqan\CommunityMcp\Mcp\Tools\SearchDiscussionsTool;
use Itqan\CommunityMcp\Mcp\Tools\SearchPostsTool;
use Itqan\CommunityMcp\Search\ForumSearchService;
use Itqan\CommunityMcp\Support\ArraySettings;
use Itqan\CommunityMcp\Tests\Support\TestCase;
use ReflectionMethod;

/**
 * Vanilla Flarum (zero optional extensions) regression tests.
 *
 * `flarum/tags` may be missing entirely, or installed in `vendor/` but
 * disabled in the admin panel. Disabled extensions keep their tables but their
 * extenders do not boot, so `Discussion` has no `tags` relation: eager loading
 * it (`with(['tags'])`) or filtering through it (`whereHas('tags')`) throws
 * `RelationNotFoundException`. The same applies to optional tables such as
 * nested replies, votes, likes and badges. Every code path must degrade to a
 * clean, empty-but-valid response instead.
 */
final class VanillaFlarumTest extends TestCase
{
    public function testTagsAreDisabledWhenExtensionsEnabledDoesNotListThem(): void
    {
        $service = $this->service(
            ['extensions_enabled' => '[]'],
            ['tags' => true, 'discussion_tag' => true]
        );

        $this->assertFalse(
            $this->invoke($service, 'tagsEnabled'),
            'Installed-but-disabled flarum/tags must not be treated as enabled.'
        );
        $this->assertSame(['user', 'firstPost'], $this->invoke($service, 'discussionRelations'));
    }

    public function testTagsAreDisabledWhenTheTablesAreMissing(): void
    {
        $service = $this->service([], []);

        $this->assertFalse($this->invoke($service, 'tagsEnabled'));
        $this->assertFalse($this->invoke($service, 'discussionHasTagsRelation'));
        $this->assertSame(['user', 'firstPost'], $this->invoke($service, 'discussionRelations'));
    }

    public function testTagsAreEnabledWhenExtensionsEnabledListsThem(): void
    {
        $service = $this->service(
            ['extensions_enabled' => '["flarum-markdown","flarum-tags"]'],
            ['tags' => true, 'discussion_tag' => true]
        );

        $this->assertTrue($this->invoke($service, 'tagsEnabled'));
    }

    public function testMalformedExtensionsEnabledFallsBackToTheSchema(): void
    {
        $service = $this->service(
            ['extensions_enabled' => 'this-is-not-json'],
            ['tags' => true, 'discussion_tag' => true]
        );

        $this->assertTrue($this->invoke($service, 'tagsEnabled'));
    }

    public function testDiscussionRelationsIncludeTagsOnlyWhenTheRelationIsBooted(): void
    {
        $service = $this->service(
            ['extensions_enabled' => '["flarum-tags"]'],
            ['tags' => true, 'discussion_tag' => true]
        );

        $this->assertSame(['user', 'firstPost'], $this->invoke($service, 'discussionRelations'));

        $this->withTagsRelationRegistered(function () use ($service): void {
            $this->assertTrue($this->invoke($service, 'discussionHasTagsRelation'));
            $this->assertSame(['user', 'firstPost', 'tags'], $this->invoke($service, 'discussionRelations'));
        });
    }

    public function testTagFilterUsesTheRelationWhenItIsBooted(): void
    {
        $service = $this->service(
            ['extensions_enabled' => '["flarum-tags"]'],
            ['tags' => true, 'discussion_tag' => true]
        );

        $this->withTagsRelationRegistered(function () use ($service): void {
            $query = $this->queryRecorder();
            $this->applyFilters($service, $query, 'ai');

            $this->assertSame([['whereHas', 'tags']], $query->calls);
        });
    }

    public function testTagFilterFallsBackToThePivotTableWhenTheRelationIsNotBooted(): void
    {
        $service = $this->service(
            ['extensions_enabled' => '[]'],
            ['tags' => true, 'discussion_tag' => true]
        );

        $query = $this->queryRecorder();
        $this->applyFilters($service, $query, 'ai');

        $this->assertCount(1, $query->calls);
        $this->assertSame(['whereExists'], $query->calls[0]);
        $this->assertContains('discussion_tag', array_column($query->sub->calls, 1));
        $this->assertContains('tags', array_column($query->sub->calls, 1));
        $this->assertContains('tags.slug', array_column($query->sub->calls, 1));
    }

    public function testTagFilterMatchesNothingWhenTagsAreNotInstalled(): void
    {
        $query = $this->queryRecorder();
        $this->applyFilters($this->service([], []), $query, 'ai');

        $this->assertSame([['whereRaw', '1 = 0']], $query->calls);
    }

    public function testListTagsRendersACleanEmptyMessageWithoutTags(): void
    {
        $output = (new ListTagsTool($this->service([], [])))->execute([], new Guest());

        $this->assertStringContainsString('Total tags', $output);
        $this->assertStringContainsString('No primary categories available', $output);
        $this->assertStringContainsString('No secondary topic tags available', $output);
    }

    public function testEveryToolResourceAndEndpointSurvivesAZeroExtensionForum(): void
    {
        $service = $this->service([], []);
        $settings = new ArraySettings();
        $guest = new Guest();

        $outputs = [
            'list_tags' => (new ListTagsTool($service))->execute([], $guest),
            'search_discussions' => (new SearchDiscussionsTool($service))->execute([], $guest),
            'search_discussions_by_tag' => (new SearchDiscussionsTool($service))->execute(['tag' => 'ai'], $guest),
            'search_posts' => (new SearchPostsTool($service))->execute(['query' => 'القرآن'], $guest),
            'get_user_contributions' => (new GetUserProfileAndPostsTool($service))->execute(['username' => 'admin'], $guest),
            'itqan://overview' => (new ForumOverviewResource($service, $settings))->render($guest),
            '/llms.txt' => (new LlmsTxtController($service, new CacheRepository(new ArrayStore())))->content(),
        ];

        foreach ($outputs as $name => $output) {
            $this->assertIsString($output, $name.' must return Markdown, not throw.');
            $this->assertNotEmpty(trim($output), $name.' must return a non-empty, clean response.');
        }

        $this->assertStringContainsString('No public tags available', $outputs['/llms.txt']);
        $this->assertSame(0, $service->getForumStatistics($guest)['tags']);
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    /**
     * A service whose schema answers are fully controllable, so the tests do
     * not need a database connection.
     *
     * @param array<string, mixed> $settings
     * @param array<string, bool> $tables
     */
    private function service(array $settings, array $tables): ForumSearchService
    {
        return new class($settings, $tables) extends ForumSearchService {
            /** @var array<string, bool> */
            private array $tables;

            /**
             * @param array<string, mixed> $settings
             * @param array<string, bool> $tables
             */
            public function __construct(array $settings, array $tables)
            {
                parent::__construct(new CacheRepository(new ArrayStore()), new ArraySettings($settings));
                $this->tables = $tables;
            }

            public function hasTable(string $table): bool
            {
                return (bool) ($this->tables[$table] ?? false);
            }

            public function hasColumn(string $table, string $column): bool
            {
                return (bool) ($this->tables[$table.'.'.$column] ?? false);
            }
        };
    }

    /**
     * @return mixed
     */
    private function invoke(ForumSearchService $service, string $method, mixed ...$arguments)
    {
        $reflection = new ReflectionMethod(ForumSearchService::class, $method);
        $reflection->setAccessible(true);

        return $reflection->invoke($service, ...$arguments);
    }

    /**
     * @param mixed $query
     */
    private function applyFilters(ForumSearchService $service, $query, string $tag): void
    {
        $this->invoke($service, 'applyDiscussionFilters', $query, $tag, null);
    }

    /**
     * Register a stand-in `tags` custom relation without booting Flarum. The
     * callback is never called: the guard only checks that the relation exists.
     */
    private function withTagsRelationRegistered(callable $test): void
    {
        $saved = AbstractModel::$customRelations;

        AbstractModel::$customRelations[Discussion::class]['tags'] = static fn (): ?object => null;

        try {
            $test();
        } finally {
            AbstractModel::$customRelations = $saved;
        }
    }

    /**
     * Minimal query double that records the SQL shaping calls the service
     * makes, without touching a database.
     */
    private function queryRecorder(): object
    {
        return new class {
            /** @var array<int, array<int, mixed>> */
            public array $calls = [];

            /** @var object */
            public $sub;

            public function __construct()
            {
                $this->sub = new class {
                    /** @var array<int, array<int, mixed>> */
                    public array $calls = [];

                    public function selectRaw($expression)
                    {
                        $this->calls[] = ['selectRaw', $expression];

                        return $this;
                    }

                    public function from($table)
                    {
                        $this->calls[] = ['from', $table];

                        return $this;
                    }

                    public function join($table, $first, $operator = null, $second = null)
                    {
                        $this->calls[] = ['join', $table];

                        return $this;
                    }

                    public function whereColumn($first, $operator = null, $second = null)
                    {
                        $this->calls[] = ['whereColumn', $first, $second];

                        return $this;
                    }

                    public function where($column, $operator = null, $value = null)
                    {
                        $this->calls[] = ['where', $column, $operator, $value];

                        return $this;
                    }
                };
            }

            public function whereRaw($sql)
            {
                $this->calls[] = ['whereRaw', $sql];

                return $this;
            }

            public function whereExists($callback)
            {
                $this->calls[] = ['whereExists'];
                $callback($this->sub);

                return $this;
            }

            public function whereHas($relation, $callback = null)
            {
                $this->calls[] = ['whereHas', $relation];

                return $this;
            }
        };
    }
}
