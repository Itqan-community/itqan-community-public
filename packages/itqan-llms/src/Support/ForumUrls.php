<?php

namespace Itqan\Llms\Support;

use Flarum\Discussion\Discussion;
use Flarum\Http\SlugManager;
use Flarum\Http\UrlGenerator;

/**
 * Builds canonical URLs for discussions and the index.
 *
 * The slug is not always `id-slug`. pipecraft/flarum-ext-id-slug adds an
 * `id`-only driver that an admin can select, which makes `/d/123-slug` a URL
 * the site itself never generates. Concatenating `-` and `$discussion->slug`
 * by hand therefore produces a link that disagrees with the page's own
 * canonical URL. This asks the configured driver, which is what
 * BasicDiscussionSerializer does.
 *
 * Takes the concrete UrlGenerator rather than a RouteCollectionUrlGenerator,
 * because only the former can hand out a named collection via `to('forum')`.
 */
class ForumUrls
{
    /**
     * @var UrlGenerator
     */
    private $url;

    /**
     * @var SlugManager
     */
    private $slugs;

    public function __construct(UrlGenerator $url, SlugManager $slugs)
    {
        $this->url = $url;
        $this->slugs = $slugs;
    }

    /**
     * The discussion's own URL, without any Markdown suffix.
     */
    public function toDiscussion(Discussion $discussion): string
    {
        return $this->url->to('forum')->route('discussion', ['id' => $this->slug($discussion)]);
    }

    /**
     * The Markdown version of a discussion, i.e. the URL an LLM follows.
     */
    public function toDiscussionMarkdown(Discussion $discussion): string
    {
        return $this->toDiscussion($discussion).'.md';
    }

    /**
     * The forum URL for a discussion slug exactly as it appears in the route,
     * e.g. `42-my-title`.
     *
     * Used where the slug is already in hand, which is the common case on a
     * discussion page. It skips the slug driver and the database entirely,
     * because the route parameter is already the slug the site is using.
     */
    public function toForumDiscussion(string $slug): string
    {
        return $this->url->to('forum')->route('discussion', ['id' => $slug]);
    }

    /**
     * The index that describes every page under the forum root.
     */
    public function toLlmsTxt(): string
    {
        // `base()` is the forum's base URL with no trailing slash, unlike
        // `path()`, which adds one.
        return $this->url->to('forum')->base().'/llms.txt';
    }

    /**
     * The slug the configured driver produces.
     *
     * `forResource()` always yields a driver for discussions: the container
     * builds that map with a fallback to the `default` entry, and the return
     * type admits no null. So there is no absent-driver case to handle here.
     */
    private function slug(Discussion $discussion): string
    {
        return $this->slugs->forResource(Discussion::class)->toSlug($discussion);
    }
}
