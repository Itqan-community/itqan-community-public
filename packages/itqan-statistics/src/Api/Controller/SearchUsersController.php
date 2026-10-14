<?php

namespace Itqan\Statistics\Api\Controller;

use Flarum\Http\RequestUtil;
use Flarum\User\User;
use Illuminate\Support\Arr;
use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

class SearchUsersController implements RequestHandlerInterface
{
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $actor = RequestUtil::getActor($request);
        $actor->assertAdmin();

        $query = $request->getQueryParams();
        $q = trim((string) Arr::get($query, 'q', ''));
        $limit = max(1, min(50, (int) Arr::get($query, 'limit', 20)));

        $builder = User::query()->orderBy('last_seen_at', 'desc')->orderBy('id', 'asc');

        if ($q !== '') {
            $like = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $q) . '%';
            $builder->where(function ($sub) use ($like, $q) {
                $sub->where('username', 'like', $like)
                    ->orWhere('email', 'like', $like);
                if (ctype_digit($q)) {
                    $sub->orWhere('id', (int) $q);
                }
            });
        }

        $users = $builder->limit($limit)->get();

        $results = $users->map(function (User $u) {
            return [
                'id' => (int) $u->id,
                'username' => $u->username,
                'display_name' => $u->display_name ?? $u->username,
                'avatar_url' => $u->avatar_url,
                'discussion_count' => (int) $u->discussion_count,
                'comment_count' => (int) $u->comment_count,
                'joined_at' => $u->joined_at ? $u->joined_at->toIso8601String() : null,
                'last_seen_at' => $u->last_seen_at ? $u->last_seen_at->toIso8601String() : null,
            ];
        })->values()->all();

        return new JsonResponse(['users' => $results]);
    }
}
