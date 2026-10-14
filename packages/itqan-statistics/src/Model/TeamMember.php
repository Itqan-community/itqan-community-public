<?php

namespace Itqan\Statistics\Model;

use Flarum\Database\AbstractModel;
use Flarum\User\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TeamMember extends AbstractModel
{
    protected $table = 'itqan_stats_team_members';

    public $timestamps = true;

    protected $fillable = [
        'team_id',
        'user_id',
        'display_name',
        'role',
    ];

    protected $casts = [
        'id' => 'integer',
        'team_id' => 'integer',
        'user_id' => 'integer',
    ];

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'team_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
