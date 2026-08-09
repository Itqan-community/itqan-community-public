<?php

namespace Itqan\MailerLite\Api\Controllers;

use Flarum\Http\RequestUtil;
use Itqan\MailerLite\Models\MailerLiteSyncLog;
use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

class ListSyncLogsController implements RequestHandlerInterface
{
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $actor = RequestUtil::getActor($request);
        $actor->assertAdmin();

        $queryParams = $request->getQueryParams();

        $page = max(1, (int) ($queryParams['page'] ?? 1));
        $perPage = min(100, max(1, (int) ($queryParams['per_page'] ?? 20)));
        $status = $queryParams['status'] ?? null;
        $action = $queryParams['action'] ?? null;
        $userId = $queryParams['user_id'] ?? null;

        $query = MailerLiteSyncLog::query()
            ->with('user')
            ->orderByDesc('created_at');

        if ($status) {
            $query->where('status', $status);
        }

        if ($action) {
            $query->where('action', $action);
        }

        if ($userId) {
            $query->where('user_id', $userId);
        }

        $total = $query->count();
        $logs = $query
            ->offset(($page - 1) * $perPage)
            ->limit($perPage)
            ->get();

        $data = $logs->map(function ($log) {
            return [
                'id' => $log->id,
                'user_id' => $log->user_id,
                'username' => $log->user?->username,
                'email' => $log->user?->email,
                'action' => $log->action,
                'status' => $log->status,
                'group_name' => $log->group_name,
                'error_message' => $log->error_message,
                'created_at' => $log->created_at?->toIso8601String(),
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
