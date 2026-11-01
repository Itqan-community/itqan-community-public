<?php

namespace Itqan\UserBadges\BadgeCategory;

use Flarum\Database\AbstractModel;
use Itqan\UserBadges\Badge\Badge;

class BadgeCategory extends AbstractModel
{
    protected $table = 'badge_category';

    protected $casts = [
        'created_at' => 'datetime',
        'is_enabled' => 'boolean',
        'is_table' => 'boolean',
        'order' => 'integer',
    ];

    public static function build(
        string $name,
        ?string $description = null,
        bool $isEnabled = true,
        bool $isTable = true,
        int $order = 0
    ): static {
        $category = new static();
        $category->name = $name;
        $category->description = $description;
        $category->is_enabled = $isEnabled;
        $category->is_table = $isTable;
        $category->order = $order;

        return $category;
    }

    public function badges()
    {
        return $this->hasMany(Badge::class, 'badge_category_id');
    }
}
