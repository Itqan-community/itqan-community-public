<?php

namespace Itqan\Statistics\Api\Controller;

use Flarum\Http\RequestUtil;
use Itqan\Statistics\Model\Team;
use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

class ListTeamsController implements RequestHandlerInterface
{
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $actor = RequestUtil::getActor($request);
        $actor->assertAdmin();

        $teams = Team::query()
            ->with(['members.user'])
            ->orderBy('sort_order', 'asc')
            ->orderBy('id', 'asc')
            ->get();

        $data = $teams->map(function (Team $team) {
            return [
                'id' => (int) $team->id,
                'name' => $team->name,
                'description' => $team->description,
                'color' => $team->color ?: '#0d9488',
                'is_visible' => (bool) $team->is_visible,
                'sort_order' => (int) $team->sort_order,
                'members' => $team->members->map(function ($m) {
                    $u = $m->user;
                    return [
                        'id' => (int) $m->id,
                        'user_id' => (int) $m->user_id,
                        'username' => $u ? $u->username : ('user_' . $m->user_id),
                        'display_name' => $m->display_name ?: ($u ? ($u->display_name ?? $u->username) : ('user_' . $m->user_id)),
                        'avatar_url' => $u ? $u->avatar_url : null,
                        'role' => $m->role,
                    ];
                })->values()->all(),
            ];
        })->values()->all();

        return new JsonResponse(['teams' => $data]);
    }
}
