<?php

namespace Itqan\Notifications;

/**
 * Resolves the effective custom notification channel for a user across a set
 * of discussion tags.
 *
 * This does not decide Follow Tags recipient eligibility. A missing row is
 * neutral and never overrides a stored preference on another tag.
 *
 * Precedence: mute > email > alert > null.
 */
class EffectiveChannelResolver
{
    /**
     * @param array<int, string> $channelsByTagId tag_id => channel for one user
     * @param int[]              $tagIds          discussion tag IDs
     * @return string|null email|alert|mute|null
     */
    public function resolve(array $channelsByTagId, array $tagIds): ?string
    {
        $hasMute = false;
        $hasEmail = false;
        $hasAlert = false;

        foreach ($tagIds as $tagId) {
            $channel = $channelsByTagId[(int) $tagId] ?? null;

            if ($channel === TagNotificationPreference::CHANNEL_MUTE) {
                $hasMute = true;
            } elseif ($channel === TagNotificationPreference::CHANNEL_EMAIL) {
                $hasEmail = true;
            } elseif ($channel === TagNotificationPreference::CHANNEL_ALERT) {
                $hasAlert = true;
            }
        }

        if ($hasMute) {
            return TagNotificationPreference::CHANNEL_MUTE;
        }

        if ($hasEmail) {
            return TagNotificationPreference::CHANNEL_EMAIL;
        }

        if ($hasAlert) {
            return TagNotificationPreference::CHANNEL_ALERT;
        }

        return null;
    }

    /**
     * One query for the recipient set × discussion tags.
     *
     * @param int[] $userIds
     * @param int[] $tagIds
     * @return array<int, array<int, string>> user_id => [tag_id => channel]
     */
    public function loadForUsersAndTags(array $userIds, array $tagIds): array
    {
        $userIds = array_values(array_unique(array_map('intval', $userIds)));
        $tagIds = array_values(array_unique(array_map('intval', $tagIds)));

        if ($userIds === [] || $tagIds === []) {
            return [];
        }

        $rows = TagNotificationPreference::query()
            ->whereIn('user_id', $userIds)
            ->whereIn('tag_id', $tagIds)
            ->get(['user_id', 'tag_id', 'channel']);

        $map = [];

        foreach ($rows as $row) {
            $channel = (string) $row->channel;

            if (! TagNotificationPreference::isValidChannel($channel)) {
                continue;
            }

            $map[(int) $row->user_id][(int) $row->tag_id] = $channel;
        }

        return $map;
    }
}
