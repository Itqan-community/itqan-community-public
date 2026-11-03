<?php

namespace Itqan\UserBadges\Controllers;

use Flarum\Frontend\Document;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Server side of `/badges` and `/badges/{id}`; the page itself is a JS route.
 */
class BadgeOverviewController
{
    public function __invoke(Document $document, ServerRequestInterface $request): Document
    {
        return $document;
    }
}
