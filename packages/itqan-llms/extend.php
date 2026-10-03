<?php

/*
 * This file is part of itqan/flarum-llms.
 *
 * For detailed copyright and license information, please view the
 * LICENSE file that was distributed with this source code.
 */

use Flarum\Extend;
use Itqan\Llms\Content\AddAlternateLinks;
use Itqan\Llms\Controller\LlmIndexController;
use Itqan\Llms\Middleware\MarkdownDiscussionMiddleware;
use Itqan\Llms\ServiceProvider\LlmServiceProvider;

return [
    // The Markdown renderer and the URL builder are shared by the controller
    // and the head-link content class, so they are constructed once here
    // rather than resolved through the container on every request.
    (new Extend\ServiceProvider())
        ->register(LlmServiceProvider::class),

    (new Extend\Frontend('forum'))
        ->content(AddAlternateLinks::class),

    // Ahead of the router, because core's discussion route would otherwise win
    // the `/d/{id-slug}.md` URL. See MarkdownDiscussionMiddleware for why a
    // route cannot do this.
    (new Extend\Middleware('forum'))
        ->insertBefore('flarum.forum.route_resolver', MarkdownDiscussionMiddleware::class),

    // The index has no competing route, so it is registered the normal way.
    (new Extend\Routes('forum'))
        ->get('/llms.txt', 'llms.txt', LlmIndexController::class),
];
