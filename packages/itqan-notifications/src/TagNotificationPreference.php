<?php

namespace Itqan\Notifications;

use Flarum\Database\AbstractModel;
use Flarum\Tags\Tag;
use Flarum\User\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An actor's notification channel for one tag.
 *
 * Absence of a row means native Follow Tags / global notification settings.
 * A stored row never makes an unfollowed user a recipient; Follow Tags
 * eligibility stays authoritative (delivery is a later checkpoint).
 *
 * @property int $user_id
 * @property int $tag_id
 * @property string $channel  email|alert|mute
 */
class TagNotificationPreference extends AbstractModel
{
    public const CHANNEL_EMAIL = 'email';
    public const CHANNEL_ALERT = 'alert';
    public const CHANNEL_MUTE = 'mute';

    public const CHANNELS = [
        self::CHANNEL_EMAIL,
        self::CHANNEL_ALERT,
        self::CHANNEL_MUTE,
    ];

    public const RELATION = 'itqanNotificationPreference';

    protected $table = 'itqan_tag_notification_preferences';

    public $incrementing = false;

    public $timestamps = false;

    protected $primaryKey = ['user_id', 'tag_id'];

    protected $fillable = [
        'user_id',
        'tag_id',
        'channel',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function tag(): BelongsTo
    {
        return $this->belongsTo(Tag::class, 'tag_id');
    }

    public static function isValidChannel(string $channel): bool
    {
        return in_array($channel, self::CHANNELS, true);
    }

    public static function forActorAndTag(User $actor, Tag $tag): ?self
    {
        if (! $actor->exists) {
            return null;
        }

        return static::query()
            ->where('user_id', $actor->id)
            ->where('tag_id', $tag->id)
            ->first();
    }

    public static function saveFor(User $actor, Tag $tag, string $channel): self
    {
        $preference = static::forActorAndTag($actor, $tag);

        if (! $preference) {
            $preference = new static();
            $preference->user_id = $actor->id;
            $preference->tag_id = $tag->id;
        }

        $preference->channel = $channel;
        $preference->save();

        return $preference;
    }

    public static function deleteFor(User $actor, Tag $tag): void
    {
        static::query()
            ->where('user_id', $actor->id)
            ->where('tag_id', $tag->id)
            ->delete();
    }

    protected function setKeysForSaveQuery($query)
    {
        $query->where('user_id', $this->user_id)
            ->where('tag_id', $this->tag_id);

        return $query;
    }
}
