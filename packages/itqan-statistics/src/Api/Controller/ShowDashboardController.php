<?php

namespace Itqan\Statistics\Api\Controller;

use Flarum\Http\RequestUtil;
use Illuminate\Support\Arr;
use Itqan\Statistics\Service\StatisticsService;
use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

class ShowDashboardController implements RequestHandlerInterface
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

        $query = $request->getQueryParams();
        $period = (string) Arr::get($query, 'period', 'mtd');
        $from = Arr::get($query, 'from');
        $to = Arr::get($query, 'to');
        $refresh = (bool) Arr::get($query, 'refresh', false);

        $payload = $this->stats->getDashboardPayload(
            $period,
            is_string($from) ? $from : null,
            is_string($to) ? $to : null,
            $refresh
        );

        return new JsonResponse($payload);
    }
}
