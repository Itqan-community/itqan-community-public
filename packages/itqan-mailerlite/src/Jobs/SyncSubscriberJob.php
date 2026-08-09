<?php

namespace Itqan\MailerLite\Jobs;

use Flarum\Queue\AbstractJob;
use Flarum\User\User;
use Itqan\MailerLite\Api\GroupManager;
use Itqan\MailerLite\Api\MailerLiteClient;
use Itqan\MailerLite\Models\MailerLiteSyncLog;
use Itqan\MailerLite\Models\MailerLiteSubscriber;

class SyncSubscriberJob extends AbstractJob
{
    private int $userId;
    private array $initialGroups;

    public function __construct(int $userId, array $initialGroups = [])
    {
        $this->userId = $userId;
        $this->initialGroups = $initialGroups;
    }

    public function handle(MailerLiteClient $client, GroupManager $groupManager): void
    {
        if (!$client->isEnabled()) {
            return;
        }

        $user = User::find($this->userId);

        if (!$user) {
            return;
        }

        $subscriber = MailerLiteSubscriber::findOrCreateForUser($user);

        // Skip if user has unsubscribed
        if ($subscriber->sync_status === MailerLiteSubscriber::STATUS_UNSUBSCRIBED) {
            return;
        }

        // Resolve group IDs from group names
        $groupIds = [];
        foreach ($this->initialGroups as $groupSettingKey) {
            $groupId = $groupManager->getGroupId($groupSettingKey);
            if ($groupId) {
                $groupIds[] = $groupId;
            }
        }

        $requestData = [
            'email' => $user->email,
            'display_name' => $user->display_name ?? $user->username,
            'groups' => $this->initialGroups,
        ];

        $result = $client->upsertSubscriber($user, [
            'flarum_user_id' => (string) $user->id,
            'flarum_username' => $user->username,
        ], $groupIds);

        if ($result['success']) {
            $subscriberId = $result['data']['id'] ?? null;

            if ($subscriberId) {
                $subscriber->markSynced($subscriberId);

                // Update local groups tracking
                foreach ($this->initialGroups as $groupKey) {
                    $groupName = resolve('flarum.settings')->get("itqan-mailerlite.{$groupKey}");
                    if ($groupName) {
                        $subscriber->addToGroup($groupName);
                    }
                }
            }

            MailerLiteSyncLog::logSuccess(
                $this->userId,
                MailerLiteSyncLog::ACTION_SYNC,
                null,
                $requestData,
                $result['data'] ?? null
            );
        } else {
            $combined = strtolower(($result['error'] ?? '') . ' ' . ($result['response']['message'] ?? ''));
            if (strpos($combined, 'unsubscribed') !== false || strpos($combined, 'opted out') !== false) {
                $subscriber->markUnsubscribed();
            } else {
                $subscriber->markFailed();
            }

            MailerLiteSyncLog::logFailure(
                $this->userId,
                MailerLiteSyncLog::ACTION_SYNC,
                $result['error'] ?? 'Unknown error',
                null,
                $requestData,
                $result['response'] ?? null
            );
        }
    }
}
