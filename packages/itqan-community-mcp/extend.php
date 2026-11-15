<?php

/*
 * Itqan Community MCP — Model Context Protocol server and LLM endpoints.
 *
 * See README.md for the endpoint list, the tools/resources/prompts and client
 * configuration snippets.
 */

namespace Itqan\CommunityMcp;

use Flarum\Extend;
use Itqan\CommunityMcp\Http\Controller\DiscussionMarkdownController;
use Itqan\CommunityMcp\Http\Controller\LlmsTxtController;
use Itqan\CommunityMcp\Http\Controller\McpStreamableController;
use Itqan\CommunityMcp\Http\Controller\SkillMdController;
use Itqan\CommunityMcp\Http\Middleware\McpCorsAndRateLimitMiddleware;
use Itqan\CommunityMcp\Http\OptionsRouteExtender;

$apiRoutes = (new Extend\Routes('api'))
    ->post('/mcp', 'itqan.mcp.rpc', McpStreamableController::class)
    ->get('/mcp', 'itqan.mcp.info', McpStreamableController::class)
    ->get('/mcp/discussions/{id}', 'itqan.mcp.discussion.md', DiscussionMarkdownController::class)
    ->get('/mcp/SKILL.md', 'itqan.mcp.skill', SkillMdController::class);

// Flarum 2.x exposes `options()`; Flarum 1.x needs the route added manually.
if (method_exists(Extend\Routes::class, 'options')) {
    $apiRoutes->options('/mcp', 'itqan.mcp.options', McpStreamableController::class);
}

$extenders = [
    (new Extend\Frontend('admin'))
        ->js(__DIR__.'/js/dist/admin.js'),

    new Extend\Locales(__DIR__.'/resources/locale'),

    $apiRoutes,

    (new Extend\Routes('forum'))
        ->get('/llms.txt', 'itqan.mcp.llms_txt', LlmsTxtController::class),

    (new Extend\Middleware('api'))
        ->add(McpCorsAndRateLimitMiddleware::class),

    // The `/llms.txt` route lives in the forum frontend, but it is an
    // AI-facing endpoint and must honour the same kill-switch and rate limit.
    (new Extend\Middleware('forum'))
        ->add(McpCorsAndRateLimitMiddleware::class),

    (new Extend\Csrf())
        ->exemptRoute('itqan.mcp.rpc')
        ->exemptRoute('itqan.mcp.options'),

    (new Extend\Settings())
        ->default('itqan-community-mcp.enabled', true)
        ->default('itqan-community-mcp.rate_limit_per_minute', 60)
        ->default('itqan-community-mcp.max_search_results', 20)
        ->default('itqan-community-mcp.max_posts_per_page', 30),
];

if (! method_exists(Extend\Routes::class, 'options')) {
    $extenders[] = new OptionsRouteExtender();
}

return $extenders;
