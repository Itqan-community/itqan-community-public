<?php

namespace Itqan\Llms\Middleware;

use Itqan\Llms\Controller\MarkdownDiscussionController;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Serves the Markdown version of a discussion, registered ahead of Flarum's
 * router.
 *
 * A route cannot do this job. Core's discussion route is
 * `/d/{id:\d+(?:-[^/]*)?}`, and `[^/]*` matches dots, so for
 * `/d/123-my-title.md` the core pattern matches with `id` =
 * `123-my-title.md`. Whichever route is registered first wins, and core's is
 * always registered first — a route added by an extension loses. Verified with
 * the same FastRoute dispatcher Flarum uses: the Markdown URL resolves to the
 * core `discussion` route, and the request dies in DiscussionRepository
 * looking for a discussion with that id.
 *
 * Intercepting before the router is therefore the only way, and it also means
 * the `.md` request is never mistaken for a discussion page.
 */
class MarkdownDiscussionMiddleware implements MiddlewareInterface
{
    /**
     * The slug cannot contain a dot, so `/d/123.5` — which core does not match
     * either — is left alone rather than being served as a Markdown export.
     * The leading group tolerates the forum living in a subdirectory.
     */
    private const PATH_PATTERN = '~^(?:.*/)?d/(\d+(?:-[^/.]*)?)\.md$~';

    /**
     * @var MarkdownDiscussionController
     */
    protected $controller;

    public function __construct(MarkdownDiscussionController $controller)
    {
        $this->controller = $controller;
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $path = $request->getUri()->getPath();

        if (! preg_match(self::PATH_PATTERN, $path, $matches)) {
            return $handler->handle($request);
        }

        // The slug arrives in the path, so it is handed on the way the route
        // would have, and the controller does not care which form it came from.
        // A stray ?id= in the query string must not win over the path.
        $query = $request->getQueryParams();
        $query['id'] = $matches[1];

        return $this->controller->handle($request->withQueryParams($query));
    }
}
