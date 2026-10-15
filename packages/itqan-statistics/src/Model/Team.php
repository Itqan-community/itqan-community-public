<?php

namespace Itqan\Statistics\Model;

use Flarum\Database\AbstractModel;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Team extends AbstractModel
{
    protected $table = 'itqan_stats_teams';

    public $timestamps = true;

    protected $fillable = [
        'name',
        'description',
        'color',
        'is_visible',
        'sort_order',
    ];

    protected $casts = [
        'id' => 'integer',
        'is_visible' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function members(): HasMany
    {
        return $this->hasMany(TeamMember::class, 'team_id');
    }
}
