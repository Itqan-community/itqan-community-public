<?php

declare(strict_types=1);

namespace Itqan\CommunityMcp\Http;

use Flarum\Extension\Extension;
use Flarum\Extend\ExtenderInterface;
use Flarum\Http\RouteCollection;
use Flarum\Http\RouteHandlerFactory;
use Illuminate\Contracts\Container\Container;
use Itqan\CommunityMcp\Http\Controller\McpStreamableController;

/**
 * Registers `OPTIONS /api/mcp` on Flarum 1.x.
 *
 * Flarum 1.x has no `Extend\Routes::options()` helper, but browsers send a
 * preflight before any cross-origin MCP POST and Flarum resolves the route
 * before extension middleware runs. Without an OPTIONS route the request would
 * die with 405 before {@see \Itqan\CommunityMcp\Http\Middleware\McpCorsAndRateLimitMiddleware}
 * can answer it. Flarum 2.x ships `options()` natively, so `extend.php` only
 * adds this extender when that helper is missing.
 */
class OptionsRouteExtender implements ExtenderInterface
{
    public function extend(Container $container, ?Extension $extension = null)
    {
        $container->resolving(
            'flarum.api.routes',
            function (RouteCollection $routes, Container $container) {
                if (array_key_exists('itqan.mcp.options', $routes->getRoutes())) {
                    return;
                }

                /** @var RouteHandlerFactory $factory */
                $factory = $container->make(RouteHandlerFactory::class);

                $routes->addRoute(
                    'OPTIONS',
                    '/mcp',
                    'itqan.mcp.options',
                    $factory->toController(McpStreamableController::class)
                );
            }
        );
    }
}
