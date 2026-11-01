<?php

namespace Itqan\UserBadges\Notification;

use Flarum\Database\AbstractModel;
use Flarum\Notification\AlertableInterface;
use Flarum\Notification\Blueprint\BlueprintInterface;
use Flarum\User\User;
use Itqan\UserBadges\UserBadge\UserBadge;

/**
 * "You received a badge".
 *
 * Alert only, as on production. Flarum 2's alert driver requires
 * `AlertableInterface`; the 1.8 blueprint did not implement it, which is why the
 * interface is added here rather than copied.
 */
class BadgeReceivedBlueprint implements BlueprintInterface, AlertableInterface
{
    public function __construct(public UserBadge $userBadge)
    {
    }

    public function getFromUser(): ?User
    {
        return null;
    }

    public function getSubject(): ?AbstractModel
    {
        return $this->userBadge;
    }

    public function getData(): mixed
    {
        return [
            'badgeName' => $this->userBadge->badge?->name,
        ];
    }

    public static function getType(): string
    {
        return 'badgeReceived';
    }

    public static function getSubjectModel(): string
    {
        return UserBadge::class;
    }
}
