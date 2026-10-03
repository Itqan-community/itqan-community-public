<?php

namespace Itqan\Llms\Content;

use Flarum\Frontend\Document;
use Itqan\Llms\Support\ForumUrls;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * Advertises the Markdown version of a discussion in the document head.
 *
 * The llms.txt spec pairs two relations: `alternate` points at the Markdown of
 * this page, and `describedby` points at the llms.txt file covering it. Only
 * the first is emitted by most implementations, which leaves an agent that
 * landed on a thread with no pointer to the index.
 */
class AddAlternateLinks
{
    /**
     * @var ForumUrls
     */
    protected $urls;

    public function __construct(ForumUrls $urls)
    {
        $this->urls = $urls;
    }

    public function __invoke(Document $document, Request $request)
    {
        if ($request->getAttribute('routeName') !== 'discussion') {
            return;
        }

        $id = $request->getAttribute('routeParameters')['id'] ?? null;

        if (! $id) {
            return;
        }

        // Deliberately not $document->canonicalUrl. Flarum populates the
        // document by running its content callbacks in registration order, and
        // core's Discussion content — which is what sets canonicalUrl — is
        // registered lazily when the route handler runs, after every extender
        // has already registered its own. An extender that runs at boot
        // therefore never sees it. Depending on it meant the canonical branch
        // below could not execute, and the fallback re-queried the discussion on
        // every single discussion page view.
        $slug = (string) $id;
        $numericId = (int) explode('-', $slug)[0];

        if ($numericId < 1) {
            return;
        }

        // The route parameter already carries the slug the site itself is
        // using, so the URL is assembled from it and the configured driver is
        // never consulted: no query, and no chance of disagreeing with the
        // page's own canonical URL.
        $markdownUrl = $this->urls->toForumDiscussion($slug).'.md';

        $document->head[] = '<link rel="alternate" type="text/markdown" href="'.e($markdownUrl).'">';
        $document->head[] = '<link rel="describedby" href="'.e($this->urls->toLlmsTxt()).'">';
    }
}
