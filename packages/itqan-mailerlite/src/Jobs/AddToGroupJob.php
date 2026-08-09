<?php

namespace Itqan\MailerLite\Jobs;

use Flarum\Queue\AbstractJob;
use Flarum\Settings\SettingsRepositoryInterface;
use Flarum\User\User;
use Itqan\MailerLite\Api\GroupManager;
use Itqan\MailerLite\Api\MailerLiteClient;
use Itqan\MailerLite\Models\MailerLiteSyncLog;
use Itqan\MailerLite\Models\MailerLiteSubscriber;

class AddToGroupJob extends AbstractJob
{
    private int $userId;
    private string $groupSettingKey;
    private bool $isFirstPost;

    public function __construct(int $userId, string $groupSettingKey, bool $isFirstPost = false)
    {
        $this->userId = $userId;
        $this->groupSettingKey = $groupSettingKey;
        $this->isFirstPost = $isFirstPost;
    }

    public function handle(
        MailerLiteClient $client,
        GroupManager $groupManager,
        SettingsRepositoryInterface $settings
    ): void {
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

        // For first-post group, check if already processed
        if ($this->isFirstPost && $subscriber->hasFirstPost()) {
            return;
        }

        // Get group name from settings
        $groupName = $settings->get("itqan-mailerlite.{$this->groupSettingKey}");

        if (empty($groupName)) {
            return;
        }

        // Skip if already in this group
        if ($subscriber->isInGroup($groupName)) {
            return;
        }

        // Get or create the group in MailerLite
        $groupId = $groupManager->findOrCreateGroupByName($groupName);

        if (!$groupId) {
            MailerLiteSyncLog::logFailure(
                $this->userId,
                MailerLiteSyncLog::ACTION_ADD_TO_GROUP,
                'Failed to find or create group',
                $groupName
            );
            return;
        }

        // Get subscriber ID (sync first if needed)
        $subscriberId = $subscriber->mailerlite_subscriber_id;

        if (!$subscriberId) {
            // User not synced yet, sync first
            $syncResult = $client->upsertSubscriber($user, [
                'flarum_user_id' => (string) $user->id,
                'flarum_username' => $user->username,
            ], [$groupId]);

            if ($syncResult['success']) {
                $subscriberId = $syncResult['data']['id'] ?? null;
                if ($subscriberId) {
                    $subscriber->markSynced($subscriberId);
                    $subscriber->addToGroup($groupName);

                    if ($this->isFirstPost) {
                        $subscriber->markFirstPost();
                    }

                    MailerLiteSyncLog::logSuccess(
                        $this->userId,
                        MailerLiteSyncLog::ACTION_ADD_TO_GROUP,
                        $groupName,
                        ['group_id' => $groupId],
                        $syncResult['data'] ?? null
                    );
                }
            } else {
                MailerLiteSyncLog::logFailure(
                    $this->userId,
                    MailerLiteSyncLog::ACTION_ADD_TO_GROUP,
                    $syncResult['error'] ?? 'Failed to sync subscriber',
                    $groupName
                );
            }

            return;
        }

        // Add existing subscriber to group
        $result = $client->addSubscriberToGroup($subscriberId, $groupId);

        if ($result['success'] || $result['status_code'] === 200) {
            $subscriber->addToGroup($groupName);

            if ($this->isFirstPost) {
                $subscriber->markFirstPost();
            }

            MailerLiteSyncLog::logSuccess(
                $this->userId,
                MailerLiteSyncLog::ACTION_ADD_TO_GROUP,
                $groupName,
                ['subscriber_id' => $subscriberId, 'group_id' => $groupId],
                $result['data'] ?? null
            );
        } else {
            MailerLiteSyncLog::logFailure(
                $this->userId,
                MailerLiteSyncLog::ACTION_ADD_TO_GROUP,
                $result['error'] ?? 'Unknown error',
                $groupName,
                ['subscriber_id' => $subscriberId, 'group_id' => $groupId],
                $result['response'] ?? null
            );
        }
    }
}
