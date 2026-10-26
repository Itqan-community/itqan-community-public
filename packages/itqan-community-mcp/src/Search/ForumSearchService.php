<?php

declare(strict_types=1);

namespace Itqan\CommunityMcp\Search;

use Flarum\Discussion\Discussion;
use Flarum\Foundation\Config;
use Flarum\Http\UrlGenerator;
use Flarum\Post\CommentPost;
use Flarum\Post\Post;
use Flarum\Settings\SettingsRepositoryInterface;
use Flarum\Tags\Tag;
use Flarum\User\Guest;
use Flarum\User\User;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Database\ConnectionInterface;
use Itqan\CommunityMcp\Formatter\PostMarkdownFormatter;
use Itqan\CommunityMcp\Support\Entity;

/**
 * Read-only forum search and thread resolution shared by every MCP tool.
 *
 * Design rules:
 *
 * - Every query starts from `whereVisibleTo($actor)`, so it honours Flarum's
 *   policies (including the community extension that keeps soft-deleted parent
 *   posts visible as placeholders).
 * - Search hits always exclude hidden and private content, while `get_discussion`
 *   keeps hidden placeholders so a branched reply tree stays connected.
 * - Flarum 1.x and 2.x differ in their search layer. The native searcher is
 *   therefore optional: it is resolved only when it exists, and every code path
 *   has an Eloquent fallback that behaves the same on both versions.
 * - Optional community columns (`parent_id`, `reply_to_post_id`, `votes`,
 *   `reply_count`, ...) are detected through the schema builder, never assumed.
 */
class ForumSearchService
{
    public const DEFAULT_BASE_URL = 'https://community.itqan.dev';

    public const TAG_CACHE_TTL = 900;

    public const MAX_RESULT_CEILING = 25;

    public const MAX_POST_PAGE = 50;

    /**
     * Hard cap on user supplied search text. Longer input is truncated before
     * it reaches the LIKE/REPLACE chain or the Arabic normalizer, so a 1 MB
     * query cannot turn into an expensive full-table scan.
     */
    public const MAX_QUERY_LENGTH = 200;

    /**
     * Hard cap on tag slugs and usernames used as filters. Real values are
     * far shorter; the bound only stops pathological input.
     */
    public const MAX_FILTER_LENGTH = 100;

    /**
     * Schema introspection is memoized for the life of the process: these
     * answers only change when a migration runs, which restarts PHP anyway.
     *
     * @var array<string, bool>
     */
    private static array $tableCache = [];

    /**
     * @var array<string, bool>
     */
    private static array $columnCache = [];

    private CacheRepository $cache;

    private SettingsRepositoryInterface $settings;

    private ?ConnectionInterface $db;

    private ?string $baseUrl = null;

    public function __construct(
        CacheRepository $cache,
        SettingsRepositoryInterface $settings,
        ?ConnectionInterface $db = null
    ) {
        $this->cache = $cache;
        $this->settings = $settings;
        $this->db = $db ?? $this->resolveConnection();
    }

    // ------------------------------------------------------------------
    // Environment helpers
    // ------------------------------------------------------------------

    /**
     * Forum base URL without a trailing slash.
     */
    public function getBaseUrl(): string
    {
        if ($this->baseUrl !== null) {
            return $this->baseUrl;
        }

        $url = null;

        try {
            if (class_exists(Config::class) && function_exists('resolve')) {
                $config = resolve(Config::class);

                if ($config instanceof Config) {
                    $url = (string) $config->url();
                }
            }
        } catch (\Throwable) {
            // Container not booted (isolated tests, CLI tools).
        }

        if (! is_string($url) || trim($url) === '') {
            try {
                if (class_exists(UrlGenerator::class) && function_exists('resolve')) {
                    $generator = resolve(UrlGenerator::class);

                    if (is_object($generator) && method_exists($generator, 'to')) {
                        $collection = $generator->to('forum');

                        if (is_object($collection) && method_exists($collection, 'base')) {
                            $url = (string) $collection->base();
                        }
                    }
                }
            } catch (\Throwable) {
                // No route collection registered yet.
            }
        }

        $url = is_string($url) ? rtrim(trim($url), '/') : '';

        if ($url === '' || str_starts_with($url, '/')) {
            $url = self::DEFAULT_BASE_URL;
        }

        return $this->baseUrl = $url;
    }

    public function hasTable(string $table): bool
    {
        $table = trim($table);

        if ($table === '') {
            return false;
        }

        if (array_key_exists($table, self::$tableCache)) {
            return self::$tableCache[$table];
        }

        $exists = false;
        $db = $this->connection();

        if ($db !== null) {
            try {
                $exists = (bool) $db->getSchemaBuilder()->hasTable($table);
            } catch (\Throwable) {
                $exists = false;
            }
        }

        return self::$tableCache[$table] = $exists;
    }

    public function hasColumn(string $table, string $column): bool
    {
        $table = trim($table);
        $column = trim($column);

        if ($table === '' || $column === '') {
            return false;
        }

        $key = $table.'.'.$column;

        if (array_key_exists($key, self::$columnCache)) {
            return self::$columnCache[$key];
        }

        $exists = false;
        $db = $this->connection();

        if ($db !== null && $this->hasTable($table)) {
            try {
                $exists = (bool) $db->getSchemaBuilder()->hasColumn($table, $column);
            } catch (\Throwable) {
                $exists = false;
            }
        }

        return self::$columnCache[$key] = $exists;
    }

    /**
     * Forget cached schema answers (tests and long-lived workers).
     */
    public function clearSchemaCache(): void
    {
        self::$tableCache = [];
        self::$columnCache = [];
    }

    // ------------------------------------------------------------------
    // Tags
    // ------------------------------------------------------------------

    /**
     * Whether `flarum/tags` is installed, migrated **and** enabled.
     *
     * The extension can stay in `vendor/` while being disabled in the admin
     * panel: its tables still exist, but Flarum does not boot its extenders,
     * so `Discussion` has no `tags` relation. Everything tag-related is
     * therefore gated behind this check. When `extensions_enabled` is absent
     * (isolated unit tests, CLI tools) the schema is the best available
     * signal.
     */
    private function tagsEnabled(): bool
    {
        if (! class_exists(Tag::class) || ! $this->hasTable('tags') || ! $this->hasTable('discussion_tag')) {
            return false;
        }

        try {
            $raw = $this->settings->get('extensions_enabled');
        } catch (\Throwable) {
            $raw = null;
        }

        if (is_string($raw) && trim($raw) !== '') {
            $enabled = json_decode($raw, true);

            if (is_array($enabled)) {
                return in_array('flarum-tags', $enabled, true);
            }
        }

        return true;
    }

    /**
     * Whether the `tags` relation is currently booted on `Discussion`.
     *
     * Flarum 1.x registers package relations through
     * `Extend\Model()->belongsToMany('tags', ...)`, which lands in a static
     * custom-relation map rather than a real method. Laravel's `isRelation()`
     * does not see those entries, so the map is inspected directly and the
     * relation is built once as a last resort (building it does not query the
     * database). A disabled extension leaves all of these empty, which is what
     * prevents `RelationNotFoundException` from `with(['tags'])` or
     * `whereHas('tags', ...)`.
     */
    private function discussionHasTagsRelation(): bool
    {
        try {
            $discussion = new Discussion();

            if (method_exists($discussion, 'isRelation') && $discussion->isRelation('tags')) {
                return true;
            }

            if (method_exists($discussion, 'tags')) {
                return true;
            }

            if (property_exists(Discussion::class, 'customRelations')) {
                $classes = array_merge([Discussion::class], class_parents(Discussion::class) ?: []);

                foreach ($classes as $class) {
                    $relations = Discussion::$customRelations[$class] ?? null;

                    if (is_array($relations) && array_key_exists('tags', $relations)) {
                        return true;
                    }
                }
            }

            $relation = $discussion->tags();

            return $relation instanceof \Illuminate\Database\Eloquent\Relations\Relation;
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Category/topic taxonomy split into primary categories (with their child
     * categories) and secondary topic tags.
     *
     * @return array{
     *     primary: array<int, array<string, mixed>>,
     *     secondary: array<int, array<string, mixed>>,
     *     all: array<int, array<string, mixed>>,
     *     count: int
     * }
     */
    public function getTagsHierarchy(bool $includeEmpty = true, ?User $actor = null): array
    {
        // Visibility depends on the actor, so only the guest-independent
        // taxonomy is cached under the documented key. A Guest actor uses the
        // public path too: it has no permissions to see restricted tags.
        if ($actor !== null && ! $actor instanceof Guest) {
            return $this->buildTagsHierarchy($includeEmpty, $actor);
        }

        // The cache is shared by every visitor, so it must always be built as
        // a guest: restricted tags are never allowed into the public cache.
        $key = 'itqan_mcp_tags_hierarchy_v2_'.($includeEmpty ? '1' : '0');
        $guest = new Guest();

        try {
            return $this->cache->remember(
                $key,
                self::TAG_CACHE_TTL,
                fn (): array => $this->buildTagsHierarchy($includeEmpty, $guest)
            );
        } catch (\Throwable) {
            return $this->buildTagsHierarchy($includeEmpty, $guest);
        }
    }

    /**
     * @return array{
     *     primary: array<int, array<string, mixed>>,
     *     secondary: array<int, array<string, mixed>>,
     *     all: array<int, array<string, mixed>>,
     *     count: int
     * }
     */
    private function buildTagsHierarchy(bool $includeEmpty = true, ?User $actor = null): array
    {
        $empty = ['primary' => [], 'secondary' => [], 'all' => [], 'count' => 0];

        if (! $this->tagsEnabled()) {
            return $empty;
        }

        $actor = $actor ?? new Guest();

        try {
            $query = Tag::query();
            $this->applyTagVisibility($query, $actor);

            if ($this->hasColumn('tags', 'is_hidden')) {
                $query->where('tags.is_hidden', false);
            }

            if ($this->hasColumn('tags', 'position')) {
                $query->orderBy('tags.position');
            }

            if ($this->hasColumn('tags', 'discussion_count')) {
                $query->orderByDesc('tags.discussion_count');
            }

            $tags = $query->get();
        } catch (\Throwable $e) {
            $this->report($e);

            return $empty;
        }

        $rows = [];

        foreach ($tags as $tag) {
            if (! $this->tagIsVisibleTo($tag, $actor)) {
                continue;
            }

            $count = Entity::int($tag, 'discussion_count');

            if (! $includeEmpty && $count <= 0) {
                continue;
            }

            $position = Entity::attr($tag, 'position');
            $parentId = Entity::attr($tag, 'parent_id');

            $rows[(int) Entity::attr($tag, 'id')] = [
                'id' => (int) Entity::attr($tag, 'id'),
                'slug' => (string) Entity::attr($tag, 'slug', ''),
                'name' => (string) Entity::attr($tag, 'name', ''),
                'description' => (string) Entity::attr($tag, 'description', ''),
                'color' => Entity::attr($tag, 'color'),
                'icon' => Entity::attr($tag, 'icon'),
                'position' => $position !== null ? (int) $position : null,
                'parent_id' => $parentId !== null && (int) $parentId > 0 ? (int) $parentId : null,
                'discussion_count' => $count,
                'is_restricted' => Entity::bool($tag, 'is_restricted'),
                'children' => [],
            ];
        }

        foreach ($rows as $id => $row) {
            $parentId = $row['parent_id'];

            if ($parentId !== null && isset($rows[$parentId])) {
                $rows[$parentId]['children'][] = $row;
            }
        }

        $primary = [];
        $secondary = [];

        foreach ($rows as $id => $row) {
            // A tag that was attached as a child is rendered under its parent.
            if ($row['parent_id'] !== null && isset($rows[$row['parent_id']])) {
                continue;
            }

            if ($row['position'] !== null) {
                $primary[] = $row;
            } else {
                $secondary[] = $row;
            }
        }

        $sort = static function (array $a, array $b): int {
            $aPosition = $a['position'] ?? PHP_INT_MAX;
            $bPosition = $b['position'] ?? PHP_INT_MAX;

            if ($aPosition !== $bPosition) {
                return $aPosition <=> $bPosition;
            }

            if ($a['discussion_count'] !== $b['discussion_count']) {
                return $b['discussion_count'] <=> $a['discussion_count'];
            }

            return strcmp($a['name'], $b['name']);
        };

        usort($primary, $sort);
        usort($secondary, $sort);

        return [
            'primary' => $primary,
            'secondary' => $secondary,
            'all' => array_values($rows),
            'count' => count($rows),
        ];
    }

    /**
     * Apply tag visibility for an actor.
     *
     * Flarum 2.x scopes tags through `whereVisibleTo()`. Flarum 1.x ships the
     * `ScopeVisibilityTrait` on `Tag` but registers no scoper for it, so the
     * call is a no-op there and a restricted tag would otherwise be listed to
     * everyone. Guests are therefore filtered at SQL level, and every actor is
     * additionally checked per tag with Flarum's tag policy below.
     *
     * @param \Illuminate\Database\Eloquent\Builder<Tag> $query
     */
    private function applyTagVisibility($query, User $actor): void
    {
        if (method_exists(Tag::class, 'whereVisibleTo')) {
            try {
                $query->whereVisibleTo($actor);
            } catch (\Throwable $e) {
                $this->report($e);
            }
        }

        // Defence in depth for the shared public cache and for Flarum 1.x,
        // where `whereVisibleTo()` does not filter restricted tags.
        if ($actor instanceof Guest && $this->hasColumn('tags', 'is_restricted')) {
            $query->where('tags.is_restricted', false);
        }
    }

    /**
     * A restricted tag is visible only through its own `tag{id}.view`
     * permission (`TagPolicy`). Anything else is denied.
     *
     * @param mixed $tag
     */
    private function tagIsVisibleTo($tag, User $actor): bool
    {
        if (! Entity::bool($tag, 'is_restricted')) {
            return true;
        }

        try {
            if (method_exists($actor, 'can')) {
                return (bool) $actor->can('view', $tag);
            }
        } catch (\Throwable $e) {
            $this->report($e);
        }

        return false;
    }

    // ------------------------------------------------------------------
    // Discussion search
    // ------------------------------------------------------------------

    /**
     * Search public discussions.
     *
     * @return array{
     *     results: array<int, array<string, mixed>>,
     *     query: ?string,
     *     tag: ?string,
     *     author: ?string,
     *     sort: string,
     *     limit: int,
     *     offset: int,
     *     has_more: bool,
     *     expanded: bool
     * }
     */
    public function searchDiscussions(
        User $actor,
        ?string $query = null,
        ?string $tagSlug = null,
        ?string $authorUsername = null,
        string $sort = 'relevance',
        int $limit = 10,
        int $offset = 0
    ): array {
        $limit = $this->clampDiscussionLimit($limit);
        $offset = max(0, $offset);
        $sort = $this->normalizeSort($sort);
        $query = $this->normalizeQuery($query);
        $tagSlug = $this->normalizeSlug($tagSlug);
        $authorUsername = $this->normalizeUsername($authorUsername);

        // A query made only of wildcard characters has no search value. It is
        // rejected instead of being forwarded to the native searcher (which
        // would treat `%`/`_` as wildcards and return everything).
        if ($query !== '' && $this->sanitizeTerm($query) === null) {
            return [
                'results' => [],
                'query' => $query,
                'tag' => $tagSlug,
                'author' => $authorUsername,
                'sort' => $sort,
                'limit' => $limit,
                'offset' => $offset,
                'has_more' => false,
                'expanded' => false,
            ];
        }

        $models = [];
        $hasMore = false;
        $expanded = false;

        if ($query === '') {
            [$models, $hasMore] = $this->runDiscussionQuery(
                $actor,
                $tagSlug,
                $authorUsername,
                $sort,
                null,
                $offset,
                $limit
            );
        } else {
            // The native searcher cannot apply our tag/author filters, so it is
            // only a candidate source for plain keyword searches.
            $nativeIds = $tagSlug === null && $authorUsername === null
                ? $this->nativeDiscussionIds($actor, $this->sanitizeTerm($query) ?? $query, $limit + 1, $offset)
                : null;

            if ($nativeIds !== null && $nativeIds !== []) {
                $loaded = $this->loadDiscussionsInOrder($actor, $nativeIds);
                $hasMore = count($loaded) > $limit;
                $models = array_slice($loaded, 0, $limit);
            }

            if ($models === []) {
                [$models, $hasMore] = $this->runDiscussionQuery(
                    $actor,
                    $tagSlug,
                    $authorUsername,
                    $sort,
                    [$query],
                    $offset,
                    $limit
                );
            }

            // Thin exact results: retry with diacritic/hamza/taa-marbuta
            // variants and the extracted Arabic keywords.
            if (count($models) < 3 && $offset === 0) {
                $variants = $this->expandTerms($query);
                $hasArabic = preg_match('/\p{Arabic}/u', $query) === 1;

                if ($variants !== [] || $hasArabic) {
                    $remaining = $limit - count($models);

                    if ($remaining > 0) {
                        $seen = array_map(static fn ($model): int => (int) Entity::attr($model, 'id'), $models);

                        // Keep the original query in the term list so the
                        // letter-folded pass can find the other spelling
                        // (المستخدم يكتب `القران` والمخزَّن `القرآن`).
                        $terms = array_values(array_unique(array_merge([$query], $variants)));

                        [$extra, $extraMore] = $this->runDiscussionQuery(
                            $actor,
                            $tagSlug,
                            $authorUsername,
                            $sort,
                            $terms,
                            0,
                            $remaining,
                            $seen,
                            true
                        );

                        if ($extra !== []) {
                            $models = array_merge($models, $extra);
                            $expanded = true;
                            $hasMore = $hasMore || $extraMore;
                        }
                    }
                }
            }
        }

        return [
            'results' => $this->formatDiscussionResults($models, $actor, $query !== '' ? $query : null),
            'query' => $query !== '' ? $query : null,
            'tag' => $tagSlug,
            'author' => $authorUsername,
            'sort' => $sort,
            'limit' => $limit,
            'offset' => $offset,
            'has_more' => $hasMore,
            'expanded' => $expanded,
        ];
    }

    /**
     * @return array{0: array<int, Discussion>, 1: bool}
     */
    private function runDiscussionQuery(
        User $actor,
        ?string $tagSlug,
        ?string $authorUsername,
        string $sort,
        ?array $terms,
        int $offset,
        int $limit,
        array $excludeIds = [],
        bool $normalizeArabic = false
    ): array {
        try {
            $query = $this->discussionBaseQuery($actor);
            $this->applyDiscussionFilters($query, $tagSlug, $authorUsername);

            if ($terms !== null) {
                $patterns = $this->likePatterns($terms);
                $normalized = $normalizeArabic ? $this->normalizedArabicPatterns($terms) : [];

                if ($patterns !== [] || $normalized !== []) {
                    $this->matchDiscussionPatterns($query, $patterns, $normalized);
                }
            }

            if ($excludeIds !== []) {
                $query->whereNotIn('discussions.id', $excludeIds);
            }

            $this->applyDiscussionSort($query, $sort, $terms[0] ?? null);

            $models = $query
                ->with($this->discussionRelations())
                ->skip($offset)
                ->take($limit + 1)
                ->get()
                ->all();

            $hasMore = count($models) > $limit;

            if ($hasMore) {
                array_pop($models);
            }

            return [$models, $hasMore];
        } catch (\Throwable $e) {
            $this->report($e);

            return [[], false];
        }
    }

    /**
     * @return array<int, Discussion>
     */
    private function loadDiscussionsInOrder(User $actor, array $ids): array
    {
        $ids = array_values(array_filter(array_map('intval', $ids)));

        if ($ids === []) {
            return [];
        }

        try {
            $models = $this->discussionBaseQuery($actor)
                ->whereIn('discussions.id', $ids)
                ->with($this->discussionRelations())
                ->get()
                ->keyBy(static fn ($model): int => (int) Entity::attr($model, 'id'));

            $ordered = [];

            foreach ($ids as $id) {
                $model = $models->get($id);

                if ($model !== null) {
                    $ordered[] = $model;
                }
            }

            return $ordered;
        } catch (\Throwable $e) {
            $this->report($e);

            return [];
        }
    }

    /**
     * Ask Flarum's native discussion searcher for ids, when this version has it.
     *
     * Returns null when the searcher is unavailable or fails, which tells the
     * caller to use the Eloquent fallback.
     *
     * @return array<int, int>|null
     */
    private function nativeDiscussionIds(User $actor, string $query, int $limit, int $offset): ?array
    {
        if (! class_exists(\Flarum\Discussion\Search\DiscussionSearcher::class)
            || ! function_exists('resolve')) {
            return null;
        }

        try {
            $searcher = resolve(\Flarum\Discussion\Search\DiscussionSearcher::class);

            if (! is_object($searcher) || ! method_exists($searcher, 'search')) {
                return null;
            }

            $criteria = $this->buildSearchCriteria($actor, $query);

            if ($criteria === null) {
                return null;
            }

            $results = $searcher->search($criteria, $limit, $offset);

            $collection = null;

            if (is_object($results) && method_exists($results, 'getResults')) {
                $collection = $results->getResults();
            } elseif (is_object($results) && property_exists($results, 'results')) {
                $collection = $results->results;
            }

            if (! is_iterable($collection)) {
                return null;
            }

            $ids = [];

            foreach ($collection as $discussion) {
                $id = Entity::attr($discussion, 'id');

                if (is_numeric($id) && (int) $id > 0) {
                    $ids[] = (int) $id;
                }
            }

            return $ids;
        } catch (\Throwable $e) {
            // Missing full-text index, unsupported collation, gambit conflict...
            $this->report($e);

            return null;
        }
    }

    /**
     * Flarum 1.8+ uses `Flarum\Query\QueryCriteria`; older/other builds expose
     * a differently shaped criteria object. Both are constructed defensively.
     *
     * @return object|null
     */
    private function buildSearchCriteria(User $actor, string $query)
    {
        if (class_exists(\Flarum\Query\QueryCriteria::class)) {
            try {
                return new \Flarum\Query\QueryCriteria($actor, ['q' => $query], null, true);
            } catch (\Throwable) {
                // Fall through to other shapes.
            }
        }

        if (class_exists(\Flarum\Search\SearchCriteria::class)) {
            try {
                return new \Flarum\Search\SearchCriteria($actor, ['q' => $query], 0, 0, null);
            } catch (\Throwable) {
                // Constructor differs; the Eloquent fallback will be used.
            }
        }

        return null;
    }

    private function discussionBaseQuery(User $actor)
    {
        $query = Discussion::query()->whereVisibleTo($actor);

        if ($this->hasColumn('discussions', 'hidden_at')) {
            $query->whereNull('discussions.hidden_at');
        }

        if ($this->hasColumn('discussions', 'is_private')) {
            $query->where('discussions.is_private', false);
        }

        $this->applyApprovedConstraint($query, $actor, 'discussions');

        return $query;
    }

    /**
     * Defence-in-depth for `flarum/approval`.
     *
     * The approval scope is registered under the `viewPrivate` ability, not
     * the default `view` one, so `whereVisibleTo($actor)` alone is not a
     * guarantee that unapproved content is hidden (and the column does not
     * exist at all when the extension is disabled). Anyone who cannot approve
     * posts only sees approved rows.
     *
     * @param mixed $query
     */
    private function applyApprovedConstraint($query, User $actor, string $table): void
    {
        if (! $this->hasColumn($table, 'is_approved')) {
            return;
        }

        try {
            if ($actor->hasPermission('discussion.approvePosts')) {
                return;
            }
        } catch (\Throwable) {
            // No permission checker available: fail closed.
        }

        $query->where($table.'.is_approved', true);
    }

    private function applyDiscussionFilters($query, ?string $tagSlug, ?string $authorUsername): void
    {
        if ($tagSlug !== null) {
            if ($this->tagsEnabled() && $this->discussionHasTagsRelation()) {
                $query->whereHas('tags', static function ($tagQuery) use ($tagSlug): void {
                    $tagQuery->where('tags.slug', $tagSlug);
                });
            } elseif ($this->hasTable('discussion_tag') && $this->hasTable('tags')) {
                // The extension is installed and migrated but not enabled, so
                // the `tags` relation is not booted. Filter through the pivot
                // table directly instead of touching the relation.
                $query->whereExists(static function ($sub) use ($tagSlug): void {
                    $sub->selectRaw('1')
                        ->from('discussion_tag')
                        ->join('tags', 'tags.id', '=', 'discussion_tag.tag_id')
                        ->whereColumn('discussion_tag.discussion_id', 'discussions.id')
                        ->where('tags.slug', $tagSlug);
                });
            } else {
                // No tags at all: a tag filter can never match.
                $query->whereRaw('1 = 0');
            }
        }

        if ($authorUsername !== null) {
            $query->whereHas('user', static function ($userQuery) use ($authorUsername): void {
                $userQuery->where('users.username', $authorUsername);
            });
        }
    }

    /**
     * Match a discussion by its title or by any of its public comment bodies.
     *
     * @param array<int, string> $patterns
     * @param array<int, string> $normalizedPatterns Arabic letter-folded patterns
     */
    private function matchDiscussionPatterns($query, array $patterns, array $normalizedPatterns = []): void
    {
        $hasHidden = $this->hasColumn('posts', 'hidden_at');
        $hasPrivate = $this->hasColumn('posts', 'is_private');

        $query->where(static function ($group) use ($patterns, $normalizedPatterns, $hasHidden, $hasPrivate): void {
            foreach ($patterns as $pattern) {
                $group->orWhere('discussions.title', 'like', $pattern);
                $group->orWhereExists(static function ($sub) use ($pattern, $hasHidden, $hasPrivate): void {
                    $sub->selectRaw('1')
                        ->from('posts')
                        ->whereColumn('posts.discussion_id', 'discussions.id')
                        ->where('posts.type', 'comment');

                    if ($hasHidden) {
                        $sub->whereNull('posts.hidden_at');
                    }

                    if ($hasPrivate) {
                        $sub->where('posts.is_private', false);
                    }

                    $sub->where('posts.content', 'like', $pattern);
                });
            }

            foreach ($normalizedPatterns as $pattern) {
                $group->orWhereRaw(self::normalizedArabicSql('discussions.title').' LIKE ?', [$pattern]);
                $group->orWhereExists(static function ($sub) use ($pattern, $hasHidden, $hasPrivate): void {
                    $sub->selectRaw('1')
                        ->from('posts')
                        ->whereColumn('posts.discussion_id', 'discussions.id')
                        ->where('posts.type', 'comment');

                    if ($hasHidden) {
                        $sub->whereNull('posts.hidden_at');
                    }

                    if ($hasPrivate) {
                        $sub->where('posts.is_private', false);
                    }

                    $sub->whereRaw(self::normalizedArabicSql('posts.content').' LIKE ?', [$pattern]);
                });
            }
        });
    }

    /**
     * SQL expression that folds Arabic letter variants (hamza forms, alef
     * maqsura, taa marbuta, waw/yaa hamza) in a text column.
     *
     * `REPLACE()` exists on MySQL/MariaDB, SQLite and PostgreSQL, so this works
     * on every database Flarum supports. It is only used on the Arabic
     * expansion pass, where a user typed a different spelling than the stored
     * text (e.g. `القران` for `القرآن`).
     */
    private static function normalizedArabicSql(string $column): string
    {
        return "REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE({$column}, 'أ', 'ا'), 'إ', 'ا'), 'آ', 'ا'), 'ٱ', 'ا'), 'ى', 'ي'), 'ة', 'ه'), 'ؤ', 'و'), 'ئ', 'ي')";
    }

    private function applyDiscussionSort($query, string $sort, ?string $term): void
    {
        switch ($sort) {
            case 'newest':
                $query->orderByDesc('discussions.created_at');
                break;

            case 'oldest':
                $query->orderBy('discussions.created_at');
                break;

            case 'top':
                $query->orderByDesc('discussions.comment_count')
                    ->orderByDesc('discussions.last_posted_at');
                break;

            case 'latest':
                $query->orderByDesc('discussions.last_posted_at');
                break;

            case 'relevance':
            default:
                if ($term !== null && trim($term) !== '') {
                    $pattern = $this->likePattern($term);

                    if ($pattern !== null) {
                        $query->orderByRaw(
                            'CASE WHEN discussions.title LIKE ? THEN 0 ELSE 1 END',
                            [$pattern]
                        );
                    }
                }

                if ($this->hasColumn('discussions', 'is_sticky')) {
                    $query->orderByDesc('discussions.is_sticky');
                }

                if ($this->hasColumn('discussions', 'votes')) {
                    $query->orderByDesc('discussions.votes');
                }

                $query->orderByDesc('discussions.comment_count')
                    ->orderByDesc('discussions.last_posted_at');
                break;
        }

        // Stable tiebreak so pagination never repeats a row.
        $query->orderByDesc('discussions.id');
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function formatDiscussionResults(array $discussions, User $actor, ?string $query, int $snippetLength = 400): array
    {
        if ($discussions === []) {
            return [];
        }

        $baseUrl = $this->getBaseUrl();

        $ids = [];

        foreach ($discussions as $discussion) {
            $ids[] = (int) Entity::attr($discussion, 'id');
        }

        $ids = array_values(array_unique($ids));

        // One batched query finds the best matching body per discussion.
        $matchingPosts = [];

        if ($query !== null && trim($query) !== '') {
            $matchingPosts = $this->loadMatchingPosts($ids, ArabicQueryNormalizer::generateVariants($query), $actor);
        }

        $firstPosts = [];
        $firstPostByDiscussion = [];

        foreach ($discussions as $discussion) {
            $first = $this->firstPostOf($discussion, $actor);

            if ($first !== null) {
                $firstPosts[(int) Entity::attr($first, 'id')] = $first;
                $firstPostByDiscussion[(int) Entity::attr($discussion, 'id')] = $first;
            }
        }

        $metadata = $this->resolvePostThreadMetadata($firstPosts, $actor);

        $results = [];

        foreach ($discussions as $discussion) {
            $id = (int) Entity::attr($discussion, 'id');
            $firstPostId = (int) Entity::attr($discussion, 'first_post_id');
            $firstMeta = $firstPostId > 0 ? ($metadata[$firstPostId] ?? []) : [];

            $votes = Entity::attr($discussion, 'votes');

            if (! is_numeric($votes)) {
                $votes = $firstMeta['votes'] ?? 0;
            }

            $detectedLang = Entity::attr($discussion, 'detected_lang');

            if (! is_string($detectedLang) || $detectedLang === '') {
                $detectedLang = $firstMeta['detected_lang'] ?? null;
            }

            $matchedPost = $matchingPosts[$id] ?? null;
            $snippetSource = $matchedPost ?? ($firstPostByDiscussion[$id] ?? null);
            $snippet = '';

            if ($snippetSource !== null) {
                $snippet = PostMarkdownFormatter::createSnippet(
                    PostMarkdownFormatter::toMarkdown($snippetSource, 20000),
                    $query,
                    $snippetLength
                );
            }

            $commentCount = max(0, Entity::int($discussion, 'comment_count'));

            $results[] = [
                'id' => $id,
                'title' => (string) Entity::attr($discussion, 'title', ''),
                'slug' => Entity::attr($discussion, 'slug'),
                'url' => $baseUrl.'/d/'.$id,
                'author' => $this->authorPayload(Entity::attr($discussion, 'user')),
                'created_at' => Entity::date(Entity::attr($discussion, 'created_at')),
                'last_posted_at' => Entity::dateTime(Entity::attr($discussion, 'last_posted_at')),
                'is_sticky' => Entity::bool($discussion, 'is_sticky'),
                'is_private' => Entity::bool($discussion, 'is_private'),
                'comment_count' => $commentCount,
                'reply_count' => max(0, $commentCount - 1),
                'participant_count' => Entity::int($discussion, 'participant_count'),
                'votes' => (int) $votes,
                'likes_count' => (int) ($firstMeta['likes_count'] ?? 0),
                'detected_lang' => is_string($detectedLang) && $detectedLang !== '' ? $detectedLang : null,
                'tags' => $this->tagsPayload($discussion, $actor),
                'snippet' => $snippet,
                'matched_post_id' => $matchedPost !== null ? (int) Entity::attr($matchedPost, 'id') : null,
                'matched_post_number' => $matchedPost !== null ? Entity::int($matchedPost, 'number') : null,
                'matched_post_url' => $matchedPost !== null
                    ? $baseUrl.'/d/'.$id.'/'.Entity::int($matchedPost, 'number')
                    : null,
            ];
        }

        return $results;
    }

    // ------------------------------------------------------------------
    // Post search
    // ------------------------------------------------------------------

    /**
     * @return array{
     *     results: array<int, array<string, mixed>>,
     *     query: ?string,
     *     discussion_id: ?int,
     *     author: ?string,
     *     limit: int,
     *     offset: int,
     *     has_more: bool,
     *     expanded: bool
     * }
     */
    public function searchPosts(
        User $actor,
        string $query,
        ?int $discussionId = null,
        ?string $authorUsername = null,
        int $limit = 10,
        int $offset = 0
    ): array {
        $query = $this->normalizeQuery($query);
        $limit = max(1, min(self::MAX_RESULT_CEILING, $limit));
        $offset = max(0, $offset);
        $discussionId = $discussionId !== null && $discussionId > 0 ? $discussionId : null;
        $authorUsername = $this->normalizeUsername($authorUsername);

        $empty = [
            'results' => [],
            'query' => $query !== '' ? $query : null,
            'discussion_id' => $discussionId,
            'author' => $authorUsername,
            'limit' => $limit,
            'offset' => $offset,
            'has_more' => false,
            'expanded' => false,
        ];

        if ($query === '' || $this->sanitizeTerm($query) === null) {
            return $empty;
        }

        $models = [];
        $hasMore = false;
        $expanded = false;

        [$models, $hasMore] = $this->runPostQuery(
            $actor,
            [$query],
            $discussionId,
            $authorUsername,
            $offset,
            $limit
        );

        if (count($models) < 3 && $offset === 0) {
            $variants = $this->expandTerms($query);
            $hasArabic = preg_match('/\p{Arabic}/u', $query) === 1;

            if ($variants !== [] || $hasArabic) {
                $remaining = $limit - count($models);

                if ($remaining > 0) {
                    $seen = array_map(static fn ($post): int => (int) Entity::attr($post, 'id'), $models);

                    // Include the original query so the letter-folded pass can
                    // match a different hamza/yaa/taa-marbuta spelling.
                    $terms = array_values(array_unique(array_merge([$query], $variants)));

                    [$extra, $extraMore] = $this->runPostQuery(
                        $actor,
                        $terms,
                        $discussionId,
                        $authorUsername,
                        0,
                        $remaining,
                        $seen,
                        true
                    );

                    if ($extra !== []) {
                        $models = array_merge($models, $extra);
                        $expanded = true;
                        $hasMore = $hasMore || $extraMore;
                    }
                }
            }
        }

        return [
            'results' => $this->formatPostResults($models, $actor, $query),
            'query' => $query,
            'discussion_id' => $discussionId,
            'author' => $authorUsername,
            'limit' => $limit,
            'offset' => $offset,
            'has_more' => $hasMore,
            'expanded' => $expanded,
        ];
    }

    /**
     * @return array{0: array<int, Post>, 1: bool}
     */
    private function runPostQuery(
        User $actor,
        array $terms,
        ?int $discussionId,
        ?string $authorUsername,
        int $offset,
        int $limit,
        array $excludeIds = [],
        bool $normalizeArabic = false
    ): array {
        $patterns = $this->likePatterns($terms);
        $normalized = $normalizeArabic ? $this->normalizedArabicPatterns($terms) : [];

        if ($patterns === [] && $normalized === []) {
            return [[], false];
        }

        $hasHiddenDiscussion = $this->hasColumn('discussions', 'hidden_at');
        $hasPrivateDiscussion = $this->hasColumn('discussions', 'is_private');

        try {
            $query = CommentPost::query()->whereVisibleTo($actor);

            $this->applyVisiblePostConstraints($query, $actor);

            // Only posts whose parent discussion is public and visible.
            $query->whereHas('discussion', function ($discussionQuery) use ($actor, $hasHiddenDiscussion, $hasPrivateDiscussion): void {
                $discussionQuery->whereVisibleTo($actor);

                if ($hasHiddenDiscussion) {
                    $discussionQuery->whereNull('discussions.hidden_at');
                }

                if ($hasPrivateDiscussion) {
                    $discussionQuery->where('discussions.is_private', false);
                }

                $this->applyApprovedConstraint($discussionQuery, $actor, 'discussions');
            });

            if ($discussionId !== null) {
                $query->where('posts.discussion_id', $discussionId);
            }

            if ($authorUsername !== null) {
                $query->whereHas('user', static function ($userQuery) use ($authorUsername): void {
                    $userQuery->where('users.username', $authorUsername);
                });
            }

            if ($excludeIds !== []) {
                $query->whereNotIn('posts.id', $excludeIds);
            }

            $this->matchPostPatterns($query, $patterns, $normalized);

            $models = $query
                ->with(['user', 'discussion'])
                ->orderByDesc('posts.created_at')
                ->orderByDesc('posts.id')
                ->skip($offset)
                ->take($limit + 1)
                ->get()
                ->all();

            $hasMore = count($models) > $limit;

            if ($hasMore) {
                array_pop($models);
            }

            return [$models, $hasMore];
        } catch (\Throwable $e) {
            $this->report($e);

            return [[], false];
        }
    }

    /**
     * @param array<int, string> $patterns
     * @param array<int, string> $normalizedPatterns
     */
    private function matchPostPatterns($query, array $patterns, array $normalizedPatterns = []): void
    {
        $query->where(static function ($group) use ($patterns, $normalizedPatterns): void {
            foreach ($patterns as $pattern) {
                $group->orWhere('posts.content', 'like', $pattern);
            }

            foreach ($normalizedPatterns as $pattern) {
                $group->orWhereRaw(self::normalizedArabicSql('posts.content').' LIKE ?', [$pattern]);
            }
        });
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function formatPostResults(array $posts, User $actor, ?string $query, int $snippetLength = 400): array
    {
        if ($posts === []) {
            return [];
        }

        $baseUrl = $this->getBaseUrl();
        $metadata = $this->resolvePostThreadMetadata($posts, $actor);

        $results = [];

        foreach ($posts as $post) {
            $id = (int) Entity::attr($post, 'id');
            $meta = $metadata[$id] ?? [];
            $discussionId = (int) Entity::attr($post, 'discussion_id');
            $number = Entity::int($post, 'number');

            $discussion = null;

            try {
                $discussion = $post->discussion;
            } catch (\Throwable) {
                $discussion = null;
            }

            $snippet = PostMarkdownFormatter::createSnippet(
                PostMarkdownFormatter::toMarkdown($post, 20000),
                $query,
                $snippetLength
            );

            $results[] = [
                'id' => $id,
                'post_number' => $number,
                'url' => $baseUrl.'/d/'.$discussionId.'/'.$number,
                'discussion_id' => $discussionId,
                'discussion_title' => (string) Entity::attr($discussion, 'title', ''),
                'discussion_url' => $baseUrl.'/d/'.$discussionId,
                'author' => $this->authorPayload(Entity::attr($post, 'user')),
                'created_at' => Entity::date(Entity::attr($post, 'created_at')),
                'snippet' => $snippet,
                'votes' => (int) ($meta['votes'] ?? 0),
                'likes_count' => (int) ($meta['likes_count'] ?? 0),
                'reply_to_post_id' => $meta['reply_to_post_id'] ?? null,
                'parent_post_number' => $meta['parent_post_number'] ?? null,
                'parent_author_username' => $meta['parent_author_username'] ?? null,
                'parent_author_display_name' => $meta['parent_author_display_name'] ?? null,
                'depth' => (int) ($meta['depth'] ?? 0),
                'reply_count' => (int) ($meta['reply_count'] ?? 0),
                'detected_lang' => $meta['detected_lang'] ?? null,
            ];
        }

        return $results;
    }

    // ------------------------------------------------------------------
    // Thread metadata (nested replies, votes, likes)
    // ------------------------------------------------------------------

    /**
     * Resolve the reply tree metadata for a batch of posts.
     *
     * Every optional source is read in one grouped query, so this never turns
     * into a per-post N+1 query:
     *
     * - parent: `reply_to_post_id` -> `parent_id` -> `mtareq_nested_replies_parents`
     * - parent number/author: one query over `posts`, one over `users`
     * - depth: the `depth` column, or computed from the parent chain
     * - replies: `reply_count` column -> Mtareq subtree counts -> direct children
     * - votes: `posts.votes` -> Mtareq VoteCounts -> `post_votes`
     * - likes: grouped `post_likes` -> `likes_count` column
     *
     * @param iterable<Post> $posts
     * @return array<int, array{
     *     votes: int,
     *     likes_count: int,
     *     reply_to_post_id: ?int,
     *     parent_post_id: ?int,
     *     parent_post_number: ?int,
     *     parent_author_username: ?string,
     *     parent_author_display_name: ?string,
     *     depth: int,
     *     reply_count: int,
     *     detected_lang: ?string
     * }>
     */
    public function resolvePostThreadMetadata(iterable $posts, ?User $actor = null): array
    {
        /** @var array<int, Post> $byId */
        $byId = [];

        foreach ($posts as $post) {
            $id = (int) Entity::attr($post, 'id');

            if ($id > 0) {
                $byId[$id] = $post;
            }
        }

        if ($byId === []) {
            return [];
        }

        $postIds = array_keys($byId);

        $discussionIds = [];

        foreach ($byId as $post) {
            $discussionId = (int) Entity::attr($post, 'discussion_id');

            if ($discussionId > 0) {
                $discussionIds[$discussionId] = true;
            }
        }

        $nestedParents = $this->nestedParentMap($postIds);

        // 1. Resolve the direct parent of every post.
        $parents = [];

        foreach ($byId as $id => $post) {
            $parent = Entity::attr($post, 'reply_to_post_id');

            if ($parent === null || (int) $parent <= 0) {
                $parent = Entity::attr($post, 'parent_id');
            }

            if (($parent === null || (int) $parent <= 0) && isset($nestedParents[$id])) {
                $parent = $nestedParents[$id];
            }

            $parentId = is_numeric($parent) ? (int) $parent : 0;

            if ($parentId <= 0 || $parentId === $id) {
                $parentId = 0;
            }

            $parents[$id] = $parentId > 0 ? $parentId : null;
        }

        $parentIds = array_values(array_unique(array_filter($parents)));

        // 2. One query loads every referenced parent post, one loads its author.
        $parentPosts = $this->loadPostsByIds($parentIds);

        $parentUserIds = [];

        foreach ($parentPosts as $parentPost) {
            $userId = (int) Entity::attr($parentPost, 'user_id');

            if ($userId > 0) {
                $parentUserIds[$userId] = true;
            }
        }

        $parentUsers = $this->loadUsersByIds(array_keys($parentUserIds));

        // 3. Depth: trust the tree column, otherwise walk the parent chain with
        // no additional queries.
        $depthOf = [];

        foreach ($byId as $id => $post) {
            $depth = Entity::attr($post, 'depth');

            if ($depth !== null && is_numeric($depth)) {
                $depthOf[$id] = max(0, (int) $depth);
            }
        }

        $parentOf = $parents;

        foreach ($parentPosts as $parentId => $parentPost) {
            $grandParent = (int) Entity::attr($parentPost, 'parent_id');

            $parentOf[$parentId] = $grandParent > 0 && $grandParent !== $parentId ? $grandParent : null;

            if (! isset($depthOf[$parentId])) {
                $depth = Entity::attr($parentPost, 'depth');

                if ($depth !== null && is_numeric($depth)) {
                    $depthOf[$parentId] = max(0, (int) $depth);
                }
            }
        }

        // 4. Reply counts and votes/likes, batched per source.
        $replyCounts = $this->resolveReplyCounts($byId, $discussionIds);
        $votes = $this->resolveVoteCounts($byId, $actor);
        $likes = $this->resolveLikeCounts($byId);
        $hasDetectedLang = $this->hasColumn('posts', 'detected_lang');

        $metadata = [];

        foreach ($byId as $id => $post) {
            $parentId = $parents[$id];
            $parentPost = $parentId !== null ? ($parentPosts[$parentId] ?? null) : null;

            $parentNumber = null;
            $parentUsername = null;
            $parentDisplayName = null;

            if ($parentPost !== null) {
                $number = Entity::attr($parentPost, 'number');
                $parentNumber = is_numeric($number) ? (int) $number : null;

                if (! $this->postIsReadableBy($parentPost, $actor)) {
                    // A removed, private or unapproved parent stays addressable,
                    // but not attributable.
                    $parentDisplayName = Entity::DELETED_LABEL;
                } else {
                    $parentUser = $parentUsers[(int) Entity::attr($parentPost, 'user_id')] ?? null;
                    $parentUsername = Entity::username($parentUser);
                    $parentDisplayName = Entity::displayName($parentUser);
                }
            } elseif ($parentId !== null) {
                // Parent row is gone entirely; keep the id so the tree can be
                // rendered, with no number or author to show.
                $parentNumber = null;
            }

            $detectedLang = $hasDetectedLang ? Entity::attr($post, 'detected_lang') : null;

            $metadata[$id] = [
                'votes' => $votes[$id] ?? 0,
                'likes_count' => $likes[$id] ?? 0,
                'reply_to_post_id' => $parentId,
                'parent_post_id' => $parentId,
                'parent_post_number' => $parentNumber,
                'parent_author_username' => $parentUsername,
                'parent_author_display_name' => $parentDisplayName,
                'depth' => $this->resolveDepth($id, $parentOf, $depthOf),
                'reply_count' => $replyCounts[$id] ?? 0,
                'detected_lang' => is_string($detectedLang) && $detectedLang !== '' ? $detectedLang : null,
            ];
        }

        return $metadata;
    }

    /**
     * @param array<int, int|null> $parentOf
     * @param array<int, int> $depthOf
     */
    private function resolveDepth(int $id, array $parentOf, array &$depthOf): int
    {
        if (isset($depthOf[$id])) {
            return $depthOf[$id];
        }

        $chain = [];
        $current = $id;
        $guard = 0;

        while (! isset($depthOf[$current]) && isset($parentOf[$current]) && $parentOf[$current] !== null && $guard < 32) {
            if (in_array($current, $chain, true)) {
                break;
            }

            $chain[] = $current;
            $current = (int) $parentOf[$current];
            $guard++;
        }

        $depth = $depthOf[$current] ?? 0;

        foreach (array_reverse($chain) as $node) {
            $depth++;
            $depthOf[$node] = $depth;
        }

        return $depthOf[$id] ?? $depth;
    }

    /**
     * @return array<int, int>
     */
    private function nestedParentMap(array $postIds): array
    {
        if ($postIds === [] || ! $this->hasTable('mtareq_nested_replies_parents')) {
            return [];
        }

        $db = $this->connection();

        if ($db === null) {
            return [];
        }

        try {
            $rows = $db->table('mtareq_nested_replies_parents')
                ->whereIn('post_id', $postIds)
                ->get(['post_id', 'parent_post_id']);

            $map = [];

            foreach ($rows as $row) {
                $map[(int) $row->post_id] = (int) $row->parent_post_id;
            }

            return $map;
        } catch (\Throwable $e) {
            $this->report($e);

            return [];
        }
    }

    /**
     * @param array<int, Post> $byId
     * @param array<int, bool> $discussionIds
     * @return array<int, int>
     */
    private function resolveReplyCounts(array $byId, array $discussionIds): array
    {
        if ($this->hasColumn('posts', 'reply_count')) {
            $counts = [];

            foreach ($byId as $id => $post) {
                $counts[$id] = max(0, Entity::int($post, 'reply_count'));
            }

            // The denormalised counter is maintained by listeners, but bulk
            // imports and seeders can leave it behind. One grouped query over
            // the direct children keeps the tree honest without an N+1.
            foreach ($this->directChildCounts(array_keys($byId)) as $id => $direct) {
                if ($direct > ($counts[$id] ?? 0)) {
                    $counts[$id] = $direct;
                }
            }

            return $counts;
        }

        // Mtareq's topic extension counts a whole subtree per discussion in one
        // cached pass; fall back to direct children when it is not installed.
        $subtree = $this->mtareqSubtreeCounts(array_keys($discussionIds));

        if ($subtree !== []) {
            return $subtree;
        }

        return $this->directChildCounts(array_keys($byId));
    }

    /**
     * @param array<int, int> $discussionIds
     * @return array<int, int>
     */
    private function mtareqSubtreeCounts(array $discussionIds): array
    {
        if (! class_exists(\Mtareq\NestedReplies\PostReply::class)
            || ! $this->hasTable('mtareq_nested_replies_parents')) {
            return [];
        }

        $counts = [];

        foreach ($discussionIds as $discussionId) {
            try {
                if (method_exists(\Mtareq\NestedReplies\PostReply::class, 'subtreeCounts')) {
                    $result = \Mtareq\NestedReplies\PostReply::subtreeCounts($discussionId);

                    if (is_array($result)) {
                        $counts += array_map('intval', $result);
                    }
                }
            } catch (\Throwable $e) {
                $this->report($e);
            }
        }

        return $counts;
    }

    /**
     * @param array<int, int> $postIds
     * @return array<int, int>
     */
    private function directChildCounts(array $postIds): array
    {
        if ($postIds === [] || ! $this->hasColumn('posts', 'parent_id')) {
            return [];
        }

        $db = $this->connection();

        if ($db === null) {
            return [];
        }

        try {
            $rows = $db->table('posts')
                ->whereIn('parent_id', $postIds)
                ->groupBy('parent_id')
                ->selectRaw('parent_id, COUNT(*) as total')
                ->get();

            $counts = [];

            foreach ($rows as $row) {
                $counts[(int) $row->parent_id] = (int) $row->total;
            }

            return $counts;
        } catch (\Throwable $e) {
            $this->report($e);

            return [];
        }
    }

    /**
     * @param array<int, Post> $byId
     * @return array<int, int>
     */
    private function resolveVoteCounts(array $byId, ?User $actor): array
    {
        $counts = [];

        // The denormalised counter is recomputed from the vote rows on every
        // write, so it is both the fastest and the most accurate source.
        if ($this->hasColumn('posts', 'votes')) {
            $missing = false;

            foreach ($byId as $id => $post) {
                $value = Entity::attr($post, 'votes');

                if (is_numeric($value)) {
                    $counts[$id] = (int) $value;
                } else {
                    $missing = true;
                }
            }

            if (! $missing) {
                return $counts;
            }
        }

        $postIds = array_keys($byId);

        if (class_exists(\Mtareq\NestedReplies\Vote\VoteCounts::class)
            && $this->hasTable('mtareq_nested_replies_votes')) {
            try {
                $result = \Mtareq\NestedReplies\Vote\VoteCounts::forPosts($postIds, $actor);

                if (is_array($result)) {
                    foreach ($postIds as $id) {
                        $counts[$id] = $counts[$id] ?? (int) ($result[$id] ?? 0);
                    }

                    return $counts;
                }
            } catch (\Throwable $e) {
                $this->report($e);
            }
        }

        if ($this->hasTable('post_votes')) {
            $db = $this->connection();

            if ($db !== null) {
                try {
                    $rows = $db->table('post_votes')
                        ->whereIn('post_id', $postIds)
                        ->groupBy('post_id')
                        ->selectRaw('post_id, COALESCE(SUM(value), 0) as total')
                        ->get();

                    foreach ($rows as $row) {
                        $counts[(int) $row->post_id] = (int) $row->total;
                    }
                } catch (\Throwable $e) {
                    $this->report($e);
                }
            }
        }

        foreach ($postIds as $id) {
            $counts[$id] = $counts[$id] ?? 0;
        }

        return $counts;
    }

    /**
     * @param array<int, Post> $byId
     * @return array<int, int>
     */
    private function resolveLikeCounts(array $byId): array
    {
        $counts = [];

        if ($this->hasTable('post_likes')) {
            $db = $this->connection();

            if ($db !== null) {
                try {
                    $rows = $db->table('post_likes')
                        ->whereIn('post_id', array_keys($byId))
                        ->groupBy('post_id')
                        ->selectRaw('post_id, COUNT(*) as total')
                        ->get();

                    foreach ($rows as $row) {
                        $counts[(int) $row->post_id] = (int) $row->total;
                    }
                } catch (\Throwable $e) {
                    $this->report($e);
                }
            }
        }

        foreach ($byId as $id => $post) {
            $counts[$id] = $counts[$id] ?? max(0, Entity::int($post, 'likes_count'));
        }

        return $counts;
    }

    // ------------------------------------------------------------------
    // Discussion reading (shared with Batch 3 controllers)
    // ------------------------------------------------------------------

    public function findVisibleDiscussion(int $discussionId, User $actor): ?Discussion
    {
        if ($discussionId <= 0) {
            return null;
        }

        try {
            return $this->discussionBaseQuery($actor)
                ->where('discussions.id', $discussionId)
                ->with($this->discussionRelations())
                ->first();
        } catch (\Throwable $e) {
            $this->report($e);

            return null;
        }
    }

    /**
     * A page of the discussion's comments in chronological order.
     *
     * Soft-deleted posts stay in the page on purpose: when a removed comment is
     * the parent of visible replies, dropping it would break the discussion
     * tree. `itqan/flarum-discussions` intends to keep those placeholders via a
     * visibility scoper, but the scoper registration order depends on how the
     * extension is booted (in Flarum 1.8 it runs before core's post scope and
     * has no effect). We therefore add the placeholders explicitly with one
     * `UNION ALL`, while never relaxing visibility for any other hidden post.
     *
     * Callers must mask the content and author of a hidden post; the renderer
     * in `GetDiscussionTool` does exactly that.
     *
     * @return array{posts: array<int, Post>, total: int, offset: int, limit: int}
     */
    public function getDiscussionPosts(
        Discussion $discussion,
        User $actor,
        int $offset = 0,
        int $limit = 25
    ): array {
        $offset = max(0, $offset);
        $limit = max(1, min(self::MAX_POST_PAGE, $limit));
        $discussionId = (int) Entity::attr($discussion, 'id');

        // Defence in depth: never read the posts of a discussion the actor
        // cannot see, even if a caller passes a model obtained elsewhere.
        if (! $this->discussionIsVisibleTo($discussionId, $actor)) {
            return ['posts' => [], 'total' => 0, 'offset' => $offset, 'limit' => $limit];
        }

        try {
            $visible = CommentPost::query()
                ->whereVisibleTo($actor)
                ->where('posts.discussion_id', $discussionId);

            $this->applyVisiblePostConstraints($visible, $actor);

            $placeholders = $this->placeholderPostQuery($discussionId, $visible, $actor);

            $total = (int) (clone $visible)->count();

            if ($placeholders !== null) {
                $total += (int) (clone $placeholders)->count();
            }

            $page = (clone $visible)->select('posts.*');

            if ($placeholders !== null) {
                $page->unionAll((clone $placeholders)->select('posts.*'));
            }

            $posts = $page
                ->with(['user'])
                ->orderBy('number')
                ->skip($offset)
                ->take($limit)
                ->get()
                ->all();

            return [
                'posts' => $posts,
                'total' => $total,
                'offset' => $offset,
                'limit' => $limit,
            ];
        } catch (\Throwable $e) {
            $this->report($e);

            return ['posts' => [], 'total' => 0, 'offset' => $offset, 'limit' => $limit];
        }
    }

    /**
     * Deleted posts that must remain addressable because visible replies point
     * at them. Content is never read from this query for display.
     *
     * @param \Illuminate\Database\Eloquent\Builder<CommentPost> $visibleQuery
     * @return \Illuminate\Database\Eloquent\Builder<CommentPost>|null
     */
    private function placeholderPostQuery(int $discussionId, $visibleQuery, User $actor)
    {
        $hasParent = $this->hasColumn('posts', 'parent_id');
        $hasReplyCount = $this->hasColumn('posts', 'reply_count');

        if (! $hasParent && ! $hasReplyCount) {
            return null;
        }

        $query = CommentPost::query()
            ->where('posts.discussion_id', $discussionId)
            ->where('posts.type', 'comment')
            ->whereNotNull('posts.hidden_at');

        if ($this->hasColumn('posts', 'is_private')) {
            $query->where('posts.is_private', false);
        }

        $this->applyApprovedConstraint($query, $actor, 'posts');

        $query->where(function ($group) use ($hasParent, $hasReplyCount): void {
            if ($hasReplyCount) {
                $group->orWhere('posts.reply_count', '>', 0);
            }

            if ($hasParent) {
                $group->orWhereExists(static function ($sub): void {
                    $sub->selectRaw('1')
                        ->from('posts as children')
                        ->whereColumn('children.parent_id', 'posts.id');
                });
            }
        });

        // Posts the actor may already see through the normal visibility scope
        // (moderators, or the author's own hidden posts) must not be duplicated.
        $query->whereNotIn('posts.id', (clone $visibleQuery)->select('posts.id'));

        return $query;
    }

    /**
     * Whether the discussion is visible to the actor under every constraint
     * this service applies (visibility, hidden, private, approval).
     */
    private function discussionIsVisibleTo(int $discussionId, User $actor): bool
    {
        if ($discussionId <= 0) {
            return false;
        }

        try {
            return $this->discussionBaseQuery($actor)
                ->where('discussions.id', $discussionId)
                ->exists();
        } catch (\Throwable $e) {
            $this->report($e);

            return false;
        }
    }

    public function findVisiblePost(int $postId, User $actor): ?Post
    {
        if ($postId <= 0) {
            return null;
        }

        try {
            $query = CommentPost::query()
                ->whereVisibleTo($actor)
                ->where('posts.id', $postId)
                ->with(['user', 'discussion']);

            $this->applyVisiblePostConstraints($query, $actor);

            return $query->first();
        } catch (\Throwable $e) {
            $this->report($e);

            return null;
        }
    }

    /**
     * Discussion header engagement: score, likes on the opening post, language.
     *
     * @return array{votes: int, likes_count: int, detected_lang: ?string}
     */
    public function getDiscussionEngagement(Discussion $discussion, User $actor): array
    {
        $firstPost = $this->firstPostOf($discussion, $actor);
        $meta = [];

        if ($firstPost !== null) {
            $meta = $this->resolvePostThreadMetadata([$firstPost], $actor)[(int) Entity::attr($firstPost, 'id')] ?? [];
        }

        $votes = Entity::attr($discussion, 'votes');

        if (! is_numeric($votes)) {
            $votes = $meta['votes'] ?? 0;
        }

        $detectedLang = Entity::attr($discussion, 'detected_lang');

        if (! is_string($detectedLang) || $detectedLang === '') {
            $detectedLang = $meta['detected_lang'] ?? null;
        }

        return [
            'votes' => (int) $votes,
            'likes_count' => (int) ($meta['likes_count'] ?? 0),
            'detected_lang' => is_string($detectedLang) && $detectedLang !== '' ? $detectedLang : null,
        ];
    }

    // ------------------------------------------------------------------
    // Users and statistics
    // ------------------------------------------------------------------

    public function findUserByHandle(string $handle, User $actor): ?User
    {
        $handle = trim($handle);

        if ($handle === '') {
            return null;
        }

        // Accept a full profile URL as well as `@username` and a numeric id.
        if (str_contains($handle, '://')) {
            $path = parse_url($handle, PHP_URL_PATH);

            if (is_string($path)) {
                $segments = array_values(array_filter(explode('/', $path), static fn (string $segment): bool => $segment !== ''));

                if ($segments !== []) {
                    $handle = (string) end($segments);
                }
            }
        }

        $handle = ltrim($handle, '@');

        if ($handle === '') {
            return null;
        }

        try {
            $query = User::query()->whereVisibleTo($actor)->with(['groups']);

            if (ctype_digit($handle)) {
                return $query->where('users.id', (int) $handle)->first();
            }

            return $query->where('users.username', $handle)->first();
        } catch (\Throwable $e) {
            $this->report($e);

            return null;
        }
    }

    /**
     * Badges from `v17development/flarum-user-badges`, when installed.
     *
     * @return array<int, array{name: string, description: string, icon: ?string}>
     */
    public function getUserBadges(int $userId): array
    {
        if ($userId <= 0 || ! $this->hasTable('badges') || ! $this->hasTable('badge_user')) {
            return [];
        }

        $db = $this->connection();

        if ($db === null) {
            return [];
        }

        try {
            $query = $db->table('badge_user as ub')
                ->join('badges as b', 'b.id', '=', 'ub.badge_id')
                ->where('ub.user_id', $userId);

            if ($this->hasColumn('badges', 'is_visible')) {
                $query->where('b.is_visible', true);
            }

            $rows = $query->orderBy('b.name')->get(['b.name', 'b.description', 'b.icon']);

            $badges = [];

            foreach ($rows as $row) {
                $badges[] = [
                    'name' => (string) ($row->name ?? ''),
                    'description' => (string) ($row->description ?? ''),
                    'icon' => isset($row->icon) ? (string) $row->icon : null,
                ];
            }

            return $badges;
        } catch (\Throwable $e) {
            $this->report($e);

            return [];
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getUserRecentDiscussions(User $user, User $actor, int $limit = 10): array
    {
        $limit = max(1, min(20, $limit));

        try {
            $models = $this->discussionBaseQuery($actor)
                ->where('discussions.user_id', (int) Entity::attr($user, 'id'))
                ->orderByDesc('discussions.created_at')
                ->take($limit)
                ->with($this->discussionRelations())
                ->get()
                ->all();

            return $this->formatDiscussionResults($models, $actor, null);
        } catch (\Throwable $e) {
            $this->report($e);

            return [];
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getUserRecentPosts(User $user, User $actor, int $limit = 10): array
    {
        $limit = max(1, min(20, $limit));
        $hasHiddenDiscussion = $this->hasColumn('discussions', 'hidden_at');
        $hasPrivateDiscussion = $this->hasColumn('discussions', 'is_private');

        try {
            $query = CommentPost::query()
                ->whereVisibleTo($actor)
                ->where('posts.user_id', (int) Entity::attr($user, 'id'));

            $this->applyVisiblePostConstraints($query, $actor);

            $query->whereHas('discussion', function ($discussionQuery) use ($actor, $hasHiddenDiscussion, $hasPrivateDiscussion): void {
                $discussionQuery->whereVisibleTo($actor);

                if ($hasHiddenDiscussion) {
                    $discussionQuery->whereNull('discussions.hidden_at');
                }

                if ($hasPrivateDiscussion) {
                    $discussionQuery->where('discussions.is_private', false);
                }

                $this->applyApprovedConstraint($discussionQuery, $actor, 'discussions');
            });

            $models = $query
                ->with(['user', 'discussion'])
                ->orderByDesc('posts.created_at')
                ->take($limit)
                ->get()
                ->all();

            return $this->formatPostResults($models, $actor, null);
        } catch (\Throwable $e) {
            $this->report($e);

            return [];
        }
    }

    /**
     * @return array{discussions: int, posts: int, tags: int, users: int}
     */
    public function getForumStatistics(User $actor): array
    {
        $stats = ['discussions' => 0, 'posts' => 0, 'tags' => 0, 'users' => 0];

        try {
            $stats['discussions'] = (int) $this->discussionBaseQuery($actor)->count();
        } catch (\Throwable $e) {
            $this->report($e);
        }

        try {
            $query = CommentPost::query()->whereVisibleTo($actor);
            $this->applyVisiblePostConstraints($query, $actor);
            $stats['posts'] = (int) $query->count();
        } catch (\Throwable $e) {
            $this->report($e);
        }

        try {
            if ($this->tagsEnabled()) {
                $query = Tag::query();
                $this->applyTagVisibility($query, $actor);

                if ($this->hasColumn('tags', 'is_hidden')) {
                    $query->where('tags.is_hidden', false);
                }

                // The overview always reports the public taxonomy count.
                if ($this->hasColumn('tags', 'is_restricted')) {
                    $query->where('tags.is_restricted', false);
                }

                $stats['tags'] = (int) $query->count();
            }
        } catch (\Throwable $e) {
            $this->report($e);
        }

        try {
            $stats['users'] = (int) User::query()->whereVisibleTo($actor)->count();
        } catch (\Throwable $e) {
            $this->report($e);
        }

        return $stats;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getStickyDiscussions(User $actor, int $limit = 5): array
    {
        $limit = max(1, min(10, $limit));

        if (! $this->hasColumn('discussions', 'is_sticky')) {
            return $this->getRecentDiscussions($actor, $limit);
        }

        try {
            $models = $this->discussionBaseQuery($actor)
                ->where('discussions.is_sticky', true)
                ->orderByDesc('discussions.last_posted_at')
                ->take($limit)
                ->with($this->discussionRelations())
                ->get()
                ->all();

            return $this->formatDiscussionResults($models, $actor, null, 200);
        } catch (\Throwable $e) {
            $this->report($e);

            return [];
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getRecentDiscussions(User $actor, int $limit = 5): array
    {
        $limit = max(1, min(10, $limit));

        try {
            $models = $this->discussionBaseQuery($actor)
                ->orderByDesc('discussions.last_posted_at')
                ->take($limit)
                ->with($this->discussionRelations())
                ->get()
                ->all();

            return $this->formatDiscussionResults($models, $actor, null, 200);
        } catch (\Throwable $e) {
            $this->report($e);

            return [];
        }
    }

    // ------------------------------------------------------------------
    // Internals
    // ------------------------------------------------------------------

    private function connection(): ?ConnectionInterface
    {
        if ($this->db !== null) {
            return $this->db;
        }

        return $this->db = $this->resolveConnection();
    }

    private function resolveConnection(): ?ConnectionInterface
    {
        if (! function_exists('resolve')) {
            return null;
        }

        try {
            $db = resolve(ConnectionInterface::class);

            return $db instanceof ConnectionInterface ? $db : null;
        } catch (\Throwable) {
            return null;
        }
    }

    private function clampDiscussionLimit(int $limit): int
    {
        $configured = 0;

        try {
            $configured = (int) $this->settings->get('itqan-community-mcp.max_search_results', 20);
        } catch (\Throwable) {
            $configured = 0;
        }

        $ceiling = $configured > 0
            ? min(self::MAX_RESULT_CEILING, $configured)
            : self::MAX_RESULT_CEILING;

        return max(1, min($limit, $ceiling));
    }

    private function normalizeSort(string $sort): string
    {
        $sort = strtolower(trim($sort));

        return in_array($sort, ['relevance', 'latest', 'top', 'newest', 'oldest'], true)
            ? $sort
            : 'relevance';
    }

    private function normalizeSlug(?string $slug): ?string
    {
        if ($slug === null) {
            return null;
        }

        $slug = mb_substr(ltrim(trim($slug), '#'), 0, self::MAX_FILTER_LENGTH, 'UTF-8');

        return $slug !== '' ? $slug : null;
    }

    private function normalizeUsername(?string $username): ?string
    {
        if ($username === null) {
            return null;
        }

        $username = mb_substr(ltrim(trim($username), '@'), 0, self::MAX_FILTER_LENGTH, 'UTF-8');

        return $username !== '' ? $username : null;
    }

    /**
     * Bound user supplied search text before any regex or SQL work happens.
     */
    private function normalizeQuery(?string $query): string
    {
        if ($query === null) {
            return '';
        }

        $query = trim($query);

        if ($query === '') {
            return '';
        }

        return mb_substr($query, 0, self::MAX_QUERY_LENGTH, 'UTF-8');
    }

    /**
     * Variants to try when the exact query is thin: diacritics, hamza forms,
     * taa-marbuta and the extracted keywords, minus the exact query itself.
     *
     * @return array<int, string>
     */
    private function expandTerms(string $query): array
    {
        $variants = [];

        foreach (ArabicQueryNormalizer::generateVariants($query) as $variant) {
            if (mb_strtolower($variant, 'UTF-8') === mb_strtolower($query, 'UTF-8')) {
                continue;
            }

            $variants[$variant] = true;
        }

        return array_keys($variants);
    }

    /**
     * @param array<int, string> $terms
     * @return array<int, string>
     */
    private function likePatterns(array $terms): array
    {
        $patterns = [];

        foreach ($terms as $term) {
            $pattern = $this->likePattern($term);

            if ($pattern !== null) {
                $patterns[$pattern] = true;
            }
        }

        return array_keys($patterns);
    }

    /**
     * Letter-folded Arabic patterns, used on the expansion pass so that
     * `القران` also finds `القرآن` (and vice versa) inside stored text.
     *
     * @param array<int, string> $terms
     * @return array<int, string>
     */
    private function normalizedArabicPatterns(array $terms): array
    {
        $patterns = [];

        foreach ($terms as $term) {
            $normalized = ArabicQueryNormalizer::normalizeLetters($term);

            if (preg_match('/\p{Arabic}/u', $normalized) !== 1) {
                continue;
            }

            $pattern = $this->likePattern($normalized);

            if ($pattern !== null) {
                $patterns[$pattern] = true;
            }
        }

        return array_keys($patterns);
    }

    /**
     * Build a safe `LIKE` pattern.
     *
     * `%`, `_` and `\` are removed: `%` and `_` are wildcards and `\` is the
     * escape character on every supported database. Stripping (rather than
     * escaping) is deliberate: SQLite does not honour a default `ESCAPE`
     * clause, so an escaped pattern would silently match a literal backslash
     * there. A query that is nothing but wildcard characters is rejected
     * instead of turning into a full-table scan.
     */
    private function likePattern(string $term): ?string
    {
        $term = $this->sanitizeTerm($term);

        return $term === null ? null : '%'.$term.'%';
    }

    /**
     * Strip characters that act as SQL wildcards/escapes and bound the length.
     * Returns null when nothing searchable is left.
     */
    private function sanitizeTerm(string $term): ?string
    {
        $term = mb_substr(trim($term), 0, self::MAX_QUERY_LENGTH, 'UTF-8');
        $term = trim(str_replace(['%', '_', '\\'], '', $term));

        if ($term === '') {
            return null;
        }

        // Everything that remains is literal text, so it cannot be a pure
        // wildcard pattern any more.
        return preg_match('/^[\s%_\\\\]+$/u', $term) === 1 ? null : $term;
    }

    /**
     * @return array<int, string>
     */
    private function discussionRelations(): array
    {
        $relations = ['user', 'firstPost'];

        if ($this->tagsEnabled() && $this->discussionHasTagsRelation()) {
            $relations[] = 'tags';
        }

        return $relations;
    }

    /**
     * @return array{id: ?int, username: ?string, display_name: string}
     */
    private function authorPayload($user): array
    {
        return [
            'id' => is_object($user) ? (int) Entity::attr($user, 'id') : null,
            'username' => Entity::username($user),
            'display_name' => Entity::displayName($user),
        ];
    }

    /**
     * @return array<int, array{slug: string, name: string}>
     */
    private function tagsPayload($discussion, ?User $actor = null): array
    {
        $tags = [];

        try {
            $related = $discussion->tags;
        } catch (\Throwable) {
            return [];
        }

        if (! is_iterable($related)) {
            return [];
        }

        foreach ($related as $tag) {
            $slug = Entity::attr($tag, 'slug');

            if (! is_string($slug) || $slug === '') {
                continue;
            }

            if ($actor !== null && (Entity::bool($tag, 'is_hidden') || ! $this->tagIsVisibleTo($tag, $actor))) {
                continue;
            }

            $tags[] = [
                'slug' => $slug,
                'name' => (string) Entity::attr($tag, 'name', ''),
            ];
        }

        return $tags;
    }

    private function firstPostOf(Discussion $discussion, ?User $actor = null): ?Post
    {
        try {
            $first = $discussion->firstPost;
        } catch (\Throwable) {
            $first = null;
        }

        if ($first instanceof Post && $this->postIsReadableBy($first, $actor)) {
            return $first;
        }

        $firstPostId = (int) Entity::attr($discussion, 'first_post_id');

        if ($firstPostId <= 0) {
            return null;
        }

        try {
            $first = Post::query()->find($firstPostId);

            return $first instanceof Post && $this->postIsReadableBy($first, $actor) ? $first : null;
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Whether a post may be shown at all: not soft-deleted, not private and,
     * when `flarum/approval` is enabled, approved (unless the actor may approve
     * posts). Callers still mask author and content of hidden placeholders;
     * this check stops private/unapproved bodies and metadata from leaking.
     *
     * @param mixed $post
     */
    private function postIsReadableBy($post, ?User $actor): bool
    {
        if (! is_object($post)) {
            return false;
        }

        if (Entity::isHidden($post)) {
            return false;
        }

        if (Entity::bool($post, 'is_private')) {
            return false;
        }

        if (! $this->hasColumn('posts', 'is_approved') || Entity::bool($post, 'is_approved')) {
            return true;
        }

        if ($actor === null) {
            return false;
        }

        try {
            return (bool) $actor->hasPermission('discussion.approvePosts');
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Apply the search-only post constraints: comments, not hidden, not private.
     */
    private function applyVisiblePostConstraints($query, ?User $actor = null): void
    {
        $query->where('posts.type', 'comment');

        if ($this->hasColumn('posts', 'hidden_at')) {
            $query->whereNull('posts.hidden_at');
        }

        if ($this->hasColumn('posts', 'is_private')) {
            $query->where('posts.is_private', false);
        }

        if ($actor !== null) {
            $this->applyApprovedConstraint($query, $actor, 'posts');
        }
    }

    /**
     * A single grouped query returns the first matching body per discussion.
     *
     * @param array<int, int> $discussionIds
     * @param array<int, string> $terms
     * @return array<int, Post>
     */
    private function loadMatchingPosts(array $discussionIds, array $terms, ?User $actor = null): array
    {
        if ($discussionIds === []) {
            return [];
        }

        $patterns = $this->likePatterns($terms);
        $normalized = $this->normalizedArabicPatterns($terms);

        if ($patterns === [] && $normalized === []) {
            return [];
        }

        try {
            $query = CommentPost::query()->whereIn('posts.discussion_id', $discussionIds);
            $this->applyVisiblePostConstraints($query, $actor);
            $this->matchPostPatterns($query, $patterns, $normalized);

            $posts = $query->orderBy('posts.number')->get();

            $result = [];

            foreach ($posts as $post) {
                $discussionId = (int) Entity::attr($post, 'discussion_id');

                if (! isset($result[$discussionId])) {
                    $result[$discussionId] = $post;
                }
            }

            return $result;
        } catch (\Throwable $e) {
            $this->report($e);

            return [];
        }
    }

    /**
     * @param array<int, int> $ids
     * @return array<int, Post>
     */
    private function loadPostsByIds(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        try {
            $posts = Post::query()->whereIn('posts.id', $ids)->get();

            $result = [];

            foreach ($posts as $post) {
                $result[(int) Entity::attr($post, 'id')] = $post;
            }

            return $result;
        } catch (\Throwable $e) {
            $this->report($e);

            return [];
        }
    }

    /**
     * @param array<int, int> $ids
     * @return array<int, User>
     */
    private function loadUsersByIds(array $ids): array
    {
        $ids = array_values(array_filter(array_map('intval', $ids)));

        if ($ids === []) {
            return [];
        }

        try {
            $users = User::query()->whereIn('users.id', $ids)->get();

            $result = [];

            foreach ($users as $user) {
                $result[(int) Entity::attr($user, 'id')] = $user;
            }

            return $result;
        } catch (\Throwable $e) {
            $this->report($e);

            return [];
        }
    }

    private function report(\Throwable $e): void
    {
        if (! function_exists('report')) {
            return;
        }

        try {
            report($e);
        } catch (\Throwable) {
            // Logging must never break a read-only tool.
        }
    }
}
