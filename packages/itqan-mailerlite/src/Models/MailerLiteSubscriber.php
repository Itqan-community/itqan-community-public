<?php

namespace Itqan\MailerLite\Models;

use Carbon\Carbon;
use Flarum\Database\AbstractModel;
use Flarum\User\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MailerLiteSubscriber extends AbstractModel
{
    protected $table = 'mailerlite_subscribers';

    protected $primaryKey = 'user_id';

    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'mailerlite_subscriber_id',
        'email',
        'sync_status',
        'groups',
        'first_post_at',
        'last_synced_at',
        'unsubscribed_at',
    ];

    protected $casts = [
        'groups' => 'array',
        'first_post_at' => 'datetime',
        'last_synced_at' => 'datetime',
        'unsubscribed_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public const STATUS_PENDING = 'pending';
    public const STATUS_SYNCED = 'synced';
    public const STATUS_FAILED = 'failed';
    public const STATUS_UNSUBSCRIBED = 'unsubscribed';

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public static function findOrCreateForUser(User $user): self
    {
        return static::firstOrCreate(
            ['user_id' => $user->id],
            [
                'email' => $user->email,
                'sync_status' => self::STATUS_PENDING,
                'groups' => [],
            ]
        );
    }

    public function markSynced(string $subscriberId): void
    {
        $this->update([
            'mailerlite_subscriber_id' => $subscriberId,
            'sync_status' => self::STATUS_SYNCED,
            'last_synced_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ]);
    }

    public function markFailed(): void
    {
        $this->update([
            'sync_status' => self::STATUS_FAILED,
            'updated_at' => Carbon::now(),
        ]);
    }

    public function markUnsubscribed(): void
    {
        $this->update([
            'sync_status' => self::STATUS_UNSUBSCRIBED,
            'unsubscribed_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ]);
    }

    public function addToGroup(string $groupName): void
    {
        $groups = $this->groups ?? [];
        if (!in_array($groupName, $groups)) {
            $groups[] = $groupName;
            $this->update([
                'groups' => $groups,
                'updated_at' => Carbon::now(),
            ]);
        }
    }

    public function removeFromGroup(string $groupName): void
    {
        $groups = $this->groups ?? [];
        $groups = array_values(array_diff($groups, [$groupName]));
        $this->update([
            'groups' => $groups,
            'updated_at' => Carbon::now(),
        ]);
    }

    public function hasFirstPost(): bool
    {
        return $this->first_post_at !== null;
    }

    public function markFirstPost(): void
    {
        if (!$this->hasFirstPost()) {
            $this->update([
                'first_post_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ]);
        }
    }

    public function isInGroup(string $groupName): bool
    {
        return in_array($groupName, $this->groups ?? []);
    }
}
