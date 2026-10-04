<?php

namespace Itqan\PreviewCards\Api;

use Flarum\Http\RequestUtil;
use Itqan\PreviewCards\Cache\CardCache;
use Laminas\Diactoros\Response;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

class ClearCacheController implements RequestHandlerInterface
{
    public function __construct(protected CardCache $cache)
    {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        RequestUtil::getActor($request)->assertAdmin();

        $result = $this->cache->clearGenerated();

        $response = new Response();
        $response->getBody()->write((string) json_encode([
            'cleared' => $result['files'],
            'freed' => $result['bytes'],
        ]));

        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(200);
    }
}
