<?php

namespace Bendary\AdminAudit\Middlewares;

use Bendary\AdminAudit\AuditLog;
use Flarum\Http\RequestUtil;
use Flarum\User\User;
use Illuminate\Support\Arr;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

class AuditAdminActionsMiddleware implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $path = $request->getUri()->getPath();
        $method = $request->getMethod();

        // ── BEFORE handler: Capture "before" state for user modifications ──
        $beforeState = null;
        if (in_array($method, ['PATCH', 'DELETE']) && preg_match('#/users/(\d+)$#', $path, $preMatches)) {
            try {
                $targetUser = User::find($preMatches[1]);
                if ($targetUser) {
                    $beforeState = [
                        'username' => $targetUser->username,
                        'email' => $targetUser->email,
                        'display_name' => $targetUser->display_name,
                        'groups' => $targetUser->groups()->pluck('name_singular', 'id')->all(),
                    ];
                }
            } catch (\Exception $e) {
                // If we can't snapshot, proceed without it
            }
        }

        // ── Execute the request ──
        $response = $handler->handle($request);
        $statusCode = $response->getStatusCode();

        // Only log successful modifying operations
        if (!in_array($statusCode, [200, 201, 204])) {
            return $response;
        }

        try {
            $actor = RequestUtil::getActor($request);
            if (!$actor || !$actor->isAdmin()) {
                return $response;
            }

            $body = $request->getParsedBody();
            $ip = Arr::get($request->getServerParams(), 'REMOTE_ADDR');

            // If parsedBody is null, try reading raw JSON body
            if ($body === null) {
                $rawBody = (string) $request->getBody();
                $body = json_decode($rawBody, true);
                try { $request->getBody()->rewind(); } catch (\Exception $e) {}
            }

            // 1. Permissions Log
            if ($method === 'POST' && (str_contains($path, '/api/permission') || str_contains($path, '/permission'))) {
                $permission = Arr::get($body, 'permission');
                $groupIds = Arr::get($body, 'groupIds', []);

                $audit = AuditLog::build(
                    $actor->id,
                    'permissions',
                    'update_permission',
                    $permission,
                    null,
                    ['group_ids' => $groupIds],
                    null,
                    $ip
                );
                $audit->save();
            }

            // 2. User modifications (Flarum strips /api prefix)
            if (in_array($method, ['POST', 'PATCH', 'DELETE']) && str_contains($path, '/users')) {
                $isUserRoute = preg_match('#/users(/\d+)?$#', $path);
                
                if ($isUserRoute) {
                    $action = 'update_user';
                    if ($method === 'POST') $action = 'create_user';
                    if ($method === 'DELETE') $action = 'delete_user';

                    // Extract target user ID from path
                    $targetId = null;
                    if (preg_match('#/users/(\d+)#', $path, $userMatches)) {
                        $targetId = $userMatches[1];
                    }

                    // Parse changes from body
                    $changes = [];
                    $safeBody = $body ?? [];
                    $attributes = Arr::get($safeBody, 'data.attributes', []);
                    $relationships = Arr::get($safeBody, 'data.relationships', []);

                    if (!empty($attributes['username'])) $changes[] = 'username';
                    if (!empty($attributes['email'])) $changes[] = 'email';
                    if (!empty($attributes['displayName'])) $changes[] = 'displayName';
                    if (isset($attributes['password'])) {
                        $changes[] = 'password';
                        if (isset($safeBody['data']['attributes']['password'])) {
                            $safeBody['data']['attributes']['password'] = '***';
                        }
                    }
                    if (!empty($relationships['groups'])) $changes[] = 'groups';
                    if (isset($attributes['isEmailConfirmed'])) $changes[] = 'emailConfirmed';
                    if (isset($attributes['nickname'])) $changes[] = 'nickname';

                    // ── Build "after" state from response ──
                    $afterState = null;
                    $targetDesc = 'User ID ' . ($targetId ?? 'New');
                    try {
                        $responseBody = (string) $response->getBody();
                        $responseData = json_decode($responseBody, true);
                        $userName = Arr::get($responseData, 'data.attributes.displayName') 
                                 ?: Arr::get($responseData, 'data.attributes.username');
                        if ($userName) {
                            $targetDesc = "User: {$userName} (ID: " . Arr::get($responseData, 'data.id', $targetId ?? 'New') . ")";
                        }

                        // Build after state from response attributes
                        $responseAttrs = Arr::get($responseData, 'data.attributes', []);
                        $afterState = [
                            'username' => Arr::get($responseAttrs, 'username'),
                            'email' => Arr::get($responseAttrs, 'email'),
                            'display_name' => Arr::get($responseAttrs, 'displayName'),
                        ];

                        // Get groups from response relationships
                        $includedGroups = Arr::get($responseData, 'included', []);
                        $groupNames = [];
                        foreach ($includedGroups as $included) {
                            if (Arr::get($included, 'type') === 'groups') {
                                $groupNames[Arr::get($included, 'id')] = Arr::get($included, 'attributes.nameSingular', Arr::get($included, 'attributes.namePlural', ''));
                            }
                        }
                        if (!empty($groupNames)) {
                            $afterState['groups'] = $groupNames;
                        }

                        $response->getBody()->rewind();
                    } catch (\Exception $e) {}

                    // ── Build the diff: only include fields that actually changed ──
                    $oldDiff = null;
                    $newDiff = null;

                    if ($beforeState && $afterState) {
                        $oldDiff = [];
                        $newDiff = [];

                        // Compare each field
                        foreach (['username', 'email', 'display_name'] as $field) {
                            $before = $beforeState[$field] ?? null;
                            $after = $afterState[$field] ?? null;
                            if ($before !== null && $after !== null && $before !== $after) {
                                $oldDiff[$field] = $before;
                                $newDiff[$field] = $after;
                            }
                        }

                        // Compare groups
                        $beforeGroups = $beforeState['groups'] ?? [];
                        $afterGroups = $afterState['groups'] ?? [];
                        if ($beforeGroups != $afterGroups) {
                            $oldDiff['groups'] = $beforeGroups;
                            $newDiff['groups'] = $afterGroups;
                        }

                        // Check for password change
                        if (in_array('password', $changes)) {
                            $oldDiff['password'] = '(unchanged)';
                            $newDiff['password'] = '(changed - redacted)';
                        }

                        // If nothing actually changed, don't show diff
                        if (empty($oldDiff)) {
                            $oldDiff = null;
                            $newDiff = null;
                        }
                    }

                    $meta = !empty($changes) ? ['modified_fields' => $changes] : null;

                    $audit = AuditLog::build(
                        $actor->id,
                        'users',
                        $action,
                        $targetDesc,
                        $oldDiff,   // old_value: what it was before
                        $newDiff ?? $safeBody,  // new_value: what it is now (fallback to raw body)
                        $meta,
                        $ip
                    );
                    $audit->save();
                }
            }

        } catch (\Exception $e) {
            // Fail silently to avoid breaking the application
        }

        return $response;
    }
}
