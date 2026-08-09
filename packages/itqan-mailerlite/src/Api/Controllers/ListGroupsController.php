<?php

namespace Itqan\MailerLite\Api\Controllers;

use Flarum\Http\RequestUtil;
use Itqan\MailerLite\Api\GroupManager;
use Itqan\MailerLite\Api\MailerLiteClient;
use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

class ListGroupsController implements RequestHandlerInterface
{
    private MailerLiteClient $client;
    private GroupManager $groupManager;

    public function __construct(MailerLiteClient $client, GroupManager $groupManager)
    {
        $this->client = $client;
        $this->groupManager = $groupManager;
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $actor = RequestUtil::getActor($request);
        $actor->assertAdmin();

        if (!$this->client->isEnabled()) {
            return new JsonResponse([
                'success' => false,
                'message' => 'MailerLite integration is disabled or API key is not configured.',
                'data' => [],
            ]);
        }

        $queryParams = $request->getQueryParams();
        $refresh = ($queryParams['refresh'] ?? '0') === '1';

        if ($refresh) {
            $this->groupManager->clearCache();
        }

        // Get all groups from MailerLite
        $result = $this->client->getGroups(100);

        if (!$result['success']) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Failed to fetch groups from MailerLite.',
                'error' => $result['error'] ?? 'Unknown error',
                'data' => [],
            ]);
        }

        $groups = $result['data'] ?? [];

        // Get configured groups
        $configuredGroups = $this->groupManager->getAllConfiguredGroups();
        $configuredGroupIds = array_column($configuredGroups, 'id');

        $data = array_map(function ($group) use ($configuredGroupIds, $configuredGroups) {
            $isConfigured = in_array($group['id'], $configuredGroupIds);
            $configKey = null;

            if ($isConfigured) {
                foreach ($configuredGroups as $key => $config) {
                    if ($config['id'] === $group['id']) {
                        $configKey = $key;
                        break;
                    }
                }
            }

            return [
                'id' => $group['id'],
                'name' => $group['name'],
                'active_count' => $group['active_count'] ?? 0,
                'sent_count' => $group['sent_count'] ?? 0,
                'opens_count' => $group['opens_count'] ?? 0,
                'clicks_count' => $group['clicks_count'] ?? 0,
                'is_configured' => $isConfigured,
                'config_key' => $configKey,
                'created_at' => $group['created_at'] ?? null,
            ];
        }, $groups);

        // Sort: configured groups first, then by name
        usort($data, function ($a, $b) {
            if ($a['is_configured'] !== $b['is_configured']) {
                return $b['is_configured'] <=> $a['is_configured'];
            }
            return strcasecmp($a['name'], $b['name']);
        });

        return new JsonResponse([
            'success' => true,
            'data' => $data,
            'configured_groups' => $configuredGroups,
        ]);
    }
}
