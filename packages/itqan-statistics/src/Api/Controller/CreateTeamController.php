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

class CreateTeamController implements RequestHandlerInterface
{
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $actor = RequestUtil::getActor($request);
        $actor->assertAdmin();

        $body = (array) $request->getParsedBody();
        $name = trim((string) Arr::get($body, 'name', ''));
        $description = trim((string) Arr::get($body, 'description', ''));
        $color = trim((string) Arr::get($body, 'color', '#0d9488'));
        $isVisible = (bool) Arr::get($body, 'is_visible', true);

        if ($name === '') {
            throw new ValidationException(['name' => 'Team name is required.']);
        }

        $maxOrder = (int) Team::query()->max('sort_order');

        $team = Team::create([
            'name' => mb_substr($name, 0, 120),
            'description' => $description !== '' ? mb_substr($description, 0, 500) : null,
            'color' => preg_match('/^#[0-9a-fA-F]{3,8}$/', $color) ? $color : '#0d9488',
            'is_visible' => $isVisible,
            'sort_order' => $maxOrder + 1,
        ]);

        return new JsonResponse([
            'team' => [
                'id' => (int) $team->id,
                'name' => $team->name,
                'description' => $team->description,
                'color' => $team->color,
                'is_visible' => (bool) $team->is_visible,
                'sort_order' => (int) $team->sort_order,
                'members' => [],
            ],
        ], 201);
    }
}
