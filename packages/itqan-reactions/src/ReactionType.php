<?php

namespace Itqan\Reactions;

use Flarum\Database\AbstractModel;

/**
 * @property int $id
 * @property string $identifier
 * @property string $emoji
 * @property string|null $label
 * @property bool $enabled
 * @property int $position
 */
class ReactionType extends AbstractModel
{
    protected $table = 'itqan_reaction_types';

    public $timestamps = false;

    protected $casts = [
        'enabled' => 'boolean',
    ];

    public function scopeEnabled($query)
    {
        return $query->where('enabled', true);
    }
}
