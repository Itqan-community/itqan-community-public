<?php

namespace Itqan\Statistics\Api\Controller;

use Flarum\Http\RequestUtil;
use Illuminate\Support\Arr;
use Itqan\Statistics\Model\Team;
use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

class DeleteTeamController implements RequestHandlerInterface
{
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $actor = RequestUtil::getActor($request);
        $actor->assertAdmin();

        $routeParams = $request->getAttribute('routeParameters', []);
        $id = (int) Arr::get($routeParams, 'id', Arr::get($request->getQueryParams(), 'id'));

        $team = Team::query()->findOrFail($id);
        $team->delete();

        return new JsonResponse(['deleted' => true, 'id' => $id]);
    }
}
