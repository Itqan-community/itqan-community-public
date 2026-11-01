<?php

namespace Itqan\UserBadges\Badge;

use Flarum\Database\AbstractModel;
use Itqan\UserBadges\BadgeCategory\BadgeCategory;
use Itqan\UserBadges\UserBadge\UserBadge;

/**
 * A badge definition.
 *
 * Table and columns are unchanged from the 1.8 extension: production already
 * holds 12 badges whose artwork lives in `image` (a plain URL to an uploaded
 * file), so the port must not reshape the data.
 */
class Badge extends AbstractModel
{
    protected $table = 'badges';

    protected $casts = [
        'created_at' => 'datetime',
        'is_visible' => 'boolean',
        'order' => 'integer',
    ];

    public static function build(
        ?string $icon,
        string $name,
        ?string $image = null,
        int $order = 0,
        ?string $description = null,
        bool $isVisible = true,
        ?string $backgroundColor = null,
        ?string $iconColor = null,
        ?string $labelColor = null
    ): static {
        $badge = new static();
        $badge->icon = $icon;
        $badge->name = $name;
        $badge->order = $order;
        $badge->image = $image;
        $badge->description = $description;
        $badge->is_visible = $isVisible;
        $badge->background_color = $backgroundColor;
        $badge->icon_color = $iconColor;
        $badge->label_color = $labelColor;

        return $badge;
    }

    public function category()
    {
        return $this->belongsTo(BadgeCategory::class, 'badge_category_id');
    }

    public function users()
    {
        return $this->hasMany(UserBadge::class, 'badge_id');
    }

    public function earnedCount(): int
    {
        return $this->users()->count();
    }
}
