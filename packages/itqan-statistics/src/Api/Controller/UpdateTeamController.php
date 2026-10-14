<?php

namespace Itqan\Statistics\Api\Controller;

use Flarum\Foundation\ValidationException;
use Flarum\Http\RequestUtil;
use Illuminate\Support\Arr;
use Itqan\Statistics\Model\Team;
use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

class UpdateTeamController implements RequestHandlerInterface
{
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $actor = RequestUtil::getActor($request);
        $actor->assertAdmin();

        $routeParams = $request->getAttribute('routeParameters', []);
        $id = (int) Arr::get($routeParams, 'id', Arr::get($request->getQueryParams(), 'id'));

        /** @var Team $team */
        $team = Team::query()->findOrFail($id);

        $body = (array) $request->getParsedBody();

        if (Arr::has($body, 'name')) {
            $name = trim((string) Arr::get($body, 'name', ''));
            if ($name === '') {
                throw new ValidationException(['name' => 'Team name cannot be empty.']);
            }
            $team->name = mb_substr($name, 0, 120);
        }

        if (Arr::has($body, 'description')) {
            $desc = trim((string) Arr::get($body, 'description', ''));
            $team->description = $desc !== '' ? mb_substr($desc, 0, 500) : null;
        }

        if (Arr::has($body, 'color')) {
            $color = trim((string) Arr::get($body, 'color', '#0d9488'));
            if (preg_match('/^#[0-9a-fA-F]{3,8}$/', $color)) {
                $team->color = $color;
            }
        }

        if (Arr::has($body, 'is_visible')) {
            $team->is_visible = (bool) Arr::get($body, 'is_visible');
        }

        if (Arr::has($body, 'sort_order')) {
            $team->sort_order = (int) Arr::get($body, 'sort_order');
        }

        $team->save();

        return new JsonResponse([
            'team' => [
                'id' => (int) $team->id,
                'name' => $team->name,
                'description' => $team->description,
                'color' => $team->color,
                'is_visible' => (bool) $team->is_visible,
                'sort_order' => (int) $team->sort_order,
            ],
        ]);
    }
}
