<?php

namespace Itqan\Statistics\Model;

use Flarum\Database\AbstractModel;

class DailyBucket extends AbstractModel
{
    protected $table = 'itqan_stats_daily_buckets';

    protected $primaryKey = 'bucket_date';
    public $incrementing = false;
    protected $keyType = 'string';

    public $timestamps = false;

    protected $fillable = [
        'bucket_date',
        'new_users_count',
        'discussions_count',
        'posts_count',
        'replies_count',
        'payload',
        'computed_at',
    ];

    protected $casts = [
        'new_users_count' => 'integer',
        'discussions_count' => 'integer',
        'posts_count' => 'integer',
        'replies_count' => 'integer',
    ];

    public function getDecodedPayload(): array
    {
        if (empty($this->payload)) {
            return [];
        }

        $decoded = json_decode($this->payload, true);
        return is_array($decoded) ? $decoded : [];
    }
}
