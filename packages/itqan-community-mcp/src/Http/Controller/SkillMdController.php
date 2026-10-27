<?php

declare(strict_types=1);

namespace Itqan\CommunityMcp\Http\Controller;

use Laminas\Diactoros\Response\TextResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * `GET /api/mcp/SKILL.md` — the packaged agent skill file.
 *
 * Served directly from `resources/skill/SKILL.md` so it is versioned with the
 * extension and stays in sync with the tool schemas.
 */
class SkillMdController implements RequestHandlerInterface
{
    private const PATH = __DIR__.'/../../../resources/skill/SKILL.md';

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $path = self::PATH;
        $content = is_readable($path) ? file_get_contents($path) : false;

        if ($content === false) {
            return new TextResponse(
                "# 404 — ملف المهارة غير موجود (Skill file not found)\n",
                404,
                [
                    'Content-Type' => 'text/markdown; charset=utf-8',
                    'X-Content-Type-Options' => 'nosniff',
                ]
            );
        }

        return new TextResponse($content, 200, [
            'Content-Type' => 'text/markdown; charset=utf-8',
            'Cache-Control' => 'public, max-age=3600',
            'Access-Control-Allow-Origin' => '*',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
