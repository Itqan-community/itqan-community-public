<?php

namespace Itqan\Statistics\Api\Controller;

use Flarum\Foundation\ValidationException;
use Flarum\Http\RequestUtil;
use Flarum\User\User;
use Illuminate\Support\Arr;
use Itqan\Statistics\Model\Team;
use Itqan\Statistics\Model\TeamMember;
use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

class AddTeamMembersController implements RequestHandlerInterface
{
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $actor = RequestUtil::getActor($request);
        $actor->assertAdmin();

        $routeParams = $request->getAttribute('routeParameters', []);
        $teamId = (int) Arr::get($routeParams, 'id', Arr::get($request->getQueryParams(), 'id'));

        /** @var Team $team */
        $team = Team::query()->findOrFail($teamId);

        $body = (array) $request->getParsedBody();

        // Support single user_id or batch user_ids[] or members[]
        $entries = [];
        if (isset($body['members']) && is_array($body['members'])) {
            $entries = $body['members'];
        } elseif (isset($body['user_ids']) && is_array($body['user_ids'])) {
            $defaultRole = trim((string) Arr::get($body, 'role', ''));
            foreach ($body['user_ids'] as $uid) {
                $entries[] = [
                    'user_id' => (int) $uid,
                    'role' => $defaultRole,
                ];
            }
        } elseif (isset($body['user_id'])) {
            $entries[] = [
                'user_id' => (int) $body['user_id'],
                'display_name' => Arr::get($body, 'display_name'),
                'role' => Arr::get($body, 'role'),
            ];
        }

        if (empty($entries)) {
            throw new ValidationException(['members' => 'At least one user must be specified.']);
        }

        $added = [];
        foreach ($entries as $item) {
            $uid = (int) Arr::get($item, 'user_id', 0);
            if ($uid <= 0) {
                continue;
            }

            $user = User::query()->find($uid);
            if (! $user) {
                continue;
            }

            $displayName = trim((string) Arr::get($item, 'display_name', ''));
            $role = trim((string) Arr::get($item, 'role', ''));

            // Enforce spec rule: a member belongs to a team via team_tag (upsert membership)
            $member = TeamMember::query()->updateOrCreate(
                [
                    'team_id' => $team->id,
                    'user_id' => $user->id,
                ],
                [
                    'display_name' => $displayName !== '' ? mb_substr($displayName, 0, 150) : ($user->display_name ?? $user->username),
                    'role' => $role !== '' ? mb_substr($role, 0, 100) : null,
                ]
            );

            $added[] = [
                'id' => (int) $member->id,
                'team_id' => (int) $team->id,
                'user_id' => (int) $user->id,
                'username' => $user->username,
                'display_name' => $member->display_name ?: $user->username,
                'role' => $member->role,
            ];
        }

        return new JsonResponse(['added' => $added]);
    }
}
