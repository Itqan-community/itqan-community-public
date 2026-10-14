<?php

namespace Itqan\Statistics\Api\Controller;

use Flarum\Http\RequestUtil;
use Illuminate\Support\Arr;
use Itqan\Statistics\Model\TeamMember;
use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

class RemoveTeamMemberController implements RequestHandlerInterface
{
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $actor = RequestUtil::getActor($request);
        $actor->assertAdmin();

        $routeParams = $request->getAttribute('routeParameters', []);
        $teamId = (int) Arr::get($routeParams, 'id', Arr::get($request->getQueryParams(), 'id'));
        $userId = (int) Arr::get($routeParams, 'userId', Arr::get($request->getQueryParams(), 'userId'));

        $deleted = TeamMember::query()
            ->where('team_id', $teamId)
            ->where('user_id', $userId)
            ->delete();

        return new JsonResponse([
            'deleted' => $deleted > 0,
            'team_id' => $teamId,
            'user_id' => $userId,
        ]);
    }
}
