<?php

namespace Itqan\Statistics\Api\Controller;

use Flarum\Http\RequestUtil;
use Illuminate\Support\Arr;
use Itqan\Statistics\Service\StatisticsService;
use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

class RebuildStatisticsController implements RequestHandlerInterface
{
    protected StatisticsService $stats;

    public function __construct(StatisticsService $stats)
    {
        $this->stats = $stats;
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $actor = RequestUtil::getActor($request);
        $actor->assertAdmin();

        $body = (array) $request->getParsedBody();
        $days = (int) Arr::get($body, 'days', 365);
        $days = max(30, min(730, $days));

        $result = $this->stats->purgeAndRebuild($days);

        return new JsonResponse($result);
    }
}
