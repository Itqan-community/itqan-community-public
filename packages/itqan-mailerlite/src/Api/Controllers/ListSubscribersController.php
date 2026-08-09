<?php

namespace Itqan\MailerLite\Api\Controllers;

use Flarum\Http\RequestUtil;
use Flarum\User\User;
use Itqan\MailerLite\Models\MailerLiteSubscriber;
use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

class ListSubscribersController implements RequestHandlerInterface
{
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $actor = RequestUtil::getActor($request);
        $actor->assertAdmin();

        $queryParams = $request->getQueryParams();

        $page = max(1, (int) ($queryParams['page'] ?? 1));
        $perPage = min(100, max(1, (int) ($queryParams['per_page'] ?? 20)));
        $status = $queryParams['status'] ?? null;
        $search = $queryParams['search'] ?? null;

        // Start with users who have confirmed emails
        $query = User::query()
            ->where('is_email_confirmed', true)
            ->whereNotNull('email');

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('username', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        // If filtering by status, join with subscribers table
        if ($status) {
            if ($status === 'not_synced') {
                $syncedUserIds = MailerLiteSubscriber::pluck('user_id');
                $query->whereNotIn('id', $syncedUserIds);
            } else {
                $query->whereIn('id', function ($subQuery) use ($status) {
                    $subQuery->select('user_id')
                        ->from('mailerlite_subscribers')
                        ->where('sync_status', $status);
                });
            }
        }

        $total = $query->count();
        $users = $query
            ->orderByDesc('id')
            ->offset(($page - 1) * $perPage)
            ->limit($perPage)
            ->get();

        // Get subscriber data for these users
        $userIds = $users->pluck('id');
        $subscribers = MailerLiteSubscriber::whereIn('user_id', $userIds)
            ->get()
            ->keyBy('user_id');

        $data = $users->map(function ($user) use ($subscribers) {
            $subscriber = $subscribers->get($user->id);

            return [
                'id' => $user->id,
                'username' => $user->username,
                'email' => $user->email,
                'display_name' => $user->display_name,
                'joined_at' => $user->joined_at?->toIso8601String(),
                'last_seen_at' => $user->last_seen_at?->toIso8601String(),
                'comment_count' => $user->comment_count,
                'sync_status' => $subscriber?->sync_status ?? 'not_synced',
                'mailerlite_subscriber_id' => $subscriber?->mailerlite_subscriber_id,
                'groups' => $subscriber?->groups ?? [],
                'first_post_at' => $subscriber?->first_post_at?->toIso8601String(),
                'last_synced_at' => $subscriber?->last_synced_at?->toIso8601String(),
            ];
        });

        return new JsonResponse([
            'data' => $data,
            'meta' => [
                'total' => $total,
                'page' => $page,
                'per_page' => $perPage,
                'total_pages' => ceil($total / $perPage),
            ],
        ]);
    }
}
