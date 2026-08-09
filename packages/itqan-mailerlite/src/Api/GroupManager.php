<?php

namespace Itqan\MailerLite\Api;

use Flarum\Settings\SettingsRepositoryInterface;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Psr\Log\LoggerInterface;

class GroupManager
{
    private const CACHE_PREFIX = 'mailerlite_group_';
    private const CACHE_TTL = 3600; // 1 hour

    private MailerLiteClient $client;
    private SettingsRepositoryInterface $settings;
    private CacheRepository $cache;
    private LoggerInterface $logger;

    public function __construct(
        MailerLiteClient $client,
        SettingsRepositoryInterface $settings,
        CacheRepository $cache,
        LoggerInterface $logger
    ) {
        $this->client = $client;
        $this->settings = $settings;
        $this->cache = $cache;
        $this->logger = $logger;
    }

    public function getGroupId(string $groupSettingKey): ?string
    {
        $groupName = $this->settings->get("itqan-mailerlite.{$groupSettingKey}");

        if (empty($groupName)) {
            return null;
        }

        return $this->findOrCreateGroupByName($groupName);
    }

    public function findOrCreateGroupByName(string $name): ?string
    {
        // Check cache first
        $cacheKey = self::CACHE_PREFIX . md5(strtolower($name));
        $cachedId = $this->cache->get($cacheKey);

        if ($cachedId !== null) {
            return $cachedId;
        }

        // Try to find existing group
        $group = $this->client->findGroupByName($name);

        if ($group !== null) {
            $this->cache->put($cacheKey, $group['id'], self::CACHE_TTL);
            return $group['id'];
        }

        // Create new group
        $result = $this->client->createGroup($name);

        if ($result['success']) {
            $groupId = $result['data']['id'];
            $this->cache->put($cacheKey, $groupId, self::CACHE_TTL);
            $this->logger->info('Created MailerLite group', ['name' => $name, 'id' => $groupId]);
            return $groupId;
        }

        $this->logger->error('Failed to create MailerLite group', [
            'name' => $name,
            'error' => $result['error'] ?? 'Unknown error',
        ]);

        return null;
    }

    public function getNewMembersGroupId(): ?string
    {
        return $this->getGroupId('group_new_members');
    }

    public function getFirstPostersGroupId(): ?string
    {
        return $this->getGroupId('group_first_posters');
    }

    public function getInactiveUsersGroupId(): ?string
    {
        return $this->getGroupId('group_inactive_users');
    }

    public function getPowerUsersGroupId(): ?string
    {
        return $this->getGroupId('group_power_users');
    }

    public function getAllConfiguredGroups(): array
    {
        $groups = [];

        $groupKeys = [
            'group_new_members',
            'group_first_posters',
            'group_inactive_users',
            'group_power_users',
        ];

        foreach ($groupKeys as $key) {
            $name = $this->settings->get("itqan-mailerlite.{$key}");
            if (!empty($name)) {
                $id = $this->findOrCreateGroupByName($name);
                if ($id !== null) {
                    $groups[$key] = [
                        'name' => $name,
                        'id' => $id,
                    ];
                }
            }
        }

        return $groups;
    }

    public function clearCache(): void
    {
        // Clear all cached group IDs by re-fetching configured groups
        $groupKeys = [
            'group_new_members',
            'group_first_posters',
            'group_inactive_users',
            'group_power_users',
        ];

        foreach ($groupKeys as $key) {
            $name = $this->settings->get("itqan-mailerlite.{$key}");
            if (!empty($name)) {
                $cacheKey = self::CACHE_PREFIX . md5(strtolower($name));
                $this->cache->forget($cacheKey);
            }
        }
    }

    public function refreshGroups(): array
    {
        $this->clearCache();
        return $this->getAllConfiguredGroups();
    }
}
