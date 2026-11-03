<?php

namespace Itqan\UserBadges\UserBadge;

use Flarum\Database\AbstractModel;
use Flarum\User\User;
use Itqan\UserBadges\Badge\Badge;

/**
 * One badge held by one member.
 *
 * `is_primary` marks the badge shown next to the name; `in_user_card` marks
 * the badges the member picked for their user card (both existing 1.8 columns).
 */
class UserBadge extends AbstractModel
{
    protected $table = 'badge_user';

    protected $casts = [
        'assigned_at' => 'datetime',
        'is_primary' => 'boolean',
        'in_user_card' => 'boolean',
    ];

    public static function build(int $userId, int $badgeId, ?string $description = null): static
    {
        $userBadge = new static();
        $userBadge->user_id = $userId;
        $userBadge->badge_id = $badgeId;
        $userBadge->description = $description;
        $userBadge->assigned_at = time();

        return $userBadge;
    }

    public function badge()
    {
        return $this->belongsTo(Badge::class, 'badge_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
