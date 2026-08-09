<?php

namespace Bendary\AdminAudit\Controllers;

use Bendary\AdminAudit\AuditLog;
use Bendary\AdminAudit\Serializers\AuditLogSerializer;
use Flarum\Api\Controller\AbstractListController;
use Flarum\Http\RequestUtil;
use Flarum\Http\UrlGenerator;
use Psr\Http\Message\ServerRequestInterface;
use Tobscure\JsonApi\Document;

class ListAuditLogsController extends AbstractListController
{
    public $serializer = AuditLogSerializer::class;

    public $include = ['user'];

    public $sortFields = ['createdAt'];

    public $sort = ['createdAt' => 'desc'];

    protected $url;

    public function __construct(UrlGenerator $url)
    {
        $this->url = $url;
    }

    protected function data(ServerRequestInterface $request, Document $document)
    {
        $actor = RequestUtil::getActor($request);
        $actor->assertAdmin();

        $filters = $this->extractFilter($request);
        $sort = $this->extractSort($request);

        $limit = $this->extractLimit($request);
        $offset = $this->extractOffset($request);

        $query = AuditLog::query();

        // Apply filters
        if (!empty($filters['user'])) {
            $query->where('user_id', $filters['user']);
        }
        if (!empty($filters['category'])) {
            if ($filters['category'] === 'sensitive') {
                $query->whereIn('category', ['permissions', 'users']);
            } else {
                $query->where('category', $filters['category']);
            }
        }
        if (!empty($filters['q'])) {
            $search = '%' . $filters['q'] . '%';
            $query->where(function ($q) use ($search) {
                $q->where('action', 'like', $search)
                  ->orWhere('target', 'like', $search);
            });
        }
        if (!empty($filters['dateRange'])) {
            $dates = explode(',', $filters['dateRange']);
            if (count($dates) == 2) {
                $query->whereBetween('created_at', [$dates[0], $dates[1]]);
            }
        }

        foreach ((array) $sort as $field => $order) {
            $query->orderBy(\Illuminate\Support\Str::snake($field), $order);
        }

        $totalCount = $query->count();

        $query->skip($offset)->take($limit);

        $results = $query->get();

        $document->addPaginationLinks(
            $this->url->to('api')->route('admin-audit.index'),
            $request->getQueryParams(),
            $offset,
            $limit,
            $results->count() ? null : 0
        );

        $sensitiveCount = AuditLog::whereIn('category', ['permissions', 'users'])->count();
        $mostActiveUserLog = AuditLog::selectRaw('user_id, count(*) as total')
            ->whereNotNull('user_id')
            ->groupBy('user_id')
            ->orderByDesc('total')
            ->first();
            
        $activeAdminName = '--';
        if ($mostActiveUserLog) {
            $adminUser = \Flarum\User\User::find($mostActiveUserLog->user_id);
            if ($adminUser) {
                $activeAdminName = $adminUser->display_name ?: $adminUser->username;
            }
        }

        $document->addMeta('total', $totalCount);
        $document->addMeta('sensitiveCount', $sensitiveCount);
        $document->addMeta('activeAdmin', $activeAdminName);

        return $results;
    }
}
