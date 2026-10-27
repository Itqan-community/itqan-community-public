<?php

declare(strict_types=1);

namespace Itqan\CommunityMcp\Support;

use Carbon\Carbon;
use DateTimeInterface;

/**
 * Attribute-safe accessors shared by the MCP tools, resources and prompts.
 *
 * Community installs run different combinations of extensions, so columns such
 * as `reply_to_post_id`, `parent_id`, `votes`, `reply_count` or `detected_lang`
 * may or may not exist. Every read therefore goes through the raw attribute bag
 * first and never assumes an accessor (or a date cast) is available.
 */
final class Entity
{
    /**
     * Label used for removed posts and deleted users, so the reply tree stays
     * readable without leaking hidden content or identity.
     */
    public const DELETED_LABEL = '[محذوف]';

    /**
     * Read a raw column value without tripping Laravel's strict attribute mode.
     *
     * @param mixed $model
     * @param mixed $default
     * @return mixed
     */
    public static function attr($model, string $key, $default = null)
    {
        if (! is_object($model)) {
            return $default;
        }

        try {
            $attributes = $model->getAttributes();

            if (is_array($attributes) && array_key_exists($key, $attributes)) {
                return $attributes[$key];
            }
        } catch (\Throwable) {
            // Not an Eloquent model; fall through to property access.
        }

        try {
            if (property_exists($model, $key)) {
                $value = $model->{$key};

                if ($value !== null) {
                    return $value;
                }
            }
        } catch (\Throwable) {
            // Property is not readable.
        }

        try {
            // `isset()` triggers Eloquent's accessor-aware __isset without
            // emitting an undefined-property warning for plain objects.
            if (isset($model->{$key})) {
                return $model->{$key};
            }
        } catch (\Throwable) {
            // Attribute or accessor unavailable.
        }

        return $default;
    }

    /**
     * @param mixed $model
     */
    public static function int($model, string $key, int $default = 0): int
    {
        $value = self::attr($model, $key);

        if (is_bool($value)) {
            return $value ? 1 : 0;
        }

        return is_numeric($value) ? (int) $value : $default;
    }

    /**
     * @param mixed $model
     */
    public static function bool($model, string $key, bool $default = false): bool
    {
        $value = self::attr($model, $key);

        if ($value === null) {
            return $default;
        }

        if (is_bool($value)) {
            return $value;
        }

        return (bool) $value;
    }

    /**
     * Whether a post or discussion is soft-deleted (`hidden_at` is set).
     *
     * Removed posts can stay visible as placeholders so a branched reply tree
     * does not break; their content and author must still be masked.
     *
     * @param mixed $model
     */
    public static function isHidden($model): bool
    {
        return self::attr($model, 'hidden_at') !== null;
    }

    /**
     * Public display name, falling back to nickname and then username.
     *
     * @param mixed $user
     */
    public static function displayName($user): string
    {
        if (! is_object($user)) {
            return self::DELETED_LABEL;
        }

        $name = null;

        try {
            $name = $user->display_name;
        } catch (\Throwable) {
            // Display-name driver not configured.
        }

        if (! is_string($name) || trim($name) === '') {
            $name = self::attr($user, 'nickname');
        }

        if (! is_string($name) || trim($name) === '') {
            $name = self::attr($user, 'username');
        }

        return is_string($name) && trim($name) !== '' ? trim($name) : self::DELETED_LABEL;
    }

    /**
     * @param mixed $user
     */
    public static function username($user): ?string
    {
        $username = self::attr($user, 'username');

        return is_string($username) && $username !== '' ? $username : null;
    }

    /**
     * `Display Name (@username)` with a graceful fallback for deleted users.
     *
     * @param mixed $user
     */
    public static function authorLabel($user): string
    {
        $name = self::displayName($user);
        $username = self::username($user);

        return $username !== null ? "{$name} (@{$username})" : $name;
    }

    /**
     * `YYYY-MM-DD` for a Carbon instance, a date string or null.
     *
     * @param mixed $value
     */
    public static function date($value): ?string
    {
        $carbon = self::toCarbon($value);

        return $carbon?->format('Y-m-d');
    }

    /**
     * `YYYY-MM-DD HH:MM` for activity timestamps.
     *
     * @param mixed $value
     */
    public static function dateTime($value): ?string
    {
        $carbon = self::toCarbon($value);

        return $carbon?->format('Y-m-d H:i');
    }

    /**
     * @param mixed $value
     */
    private static function toCarbon($value): ?Carbon
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            if ($value instanceof DateTimeInterface) {
                return Carbon::instance(
                    $value instanceof \DateTime ? $value : new \DateTime($value->format('Y-m-d H:i:s'))
                );
            }

            if (is_numeric($value)) {
                return Carbon::createFromTimestampUTC((int) $value);
            }

            return Carbon::parse((string) $value);
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * `slug (Arabic name)` list for a discussion's tags.
     *
     * @param mixed $tags
     */
    public static function tagList($tags): string
    {
        if (! is_iterable($tags)) {
            return '—';
        }

        $parts = [];

        foreach ($tags as $tag) {
            $slug = self::attr($tag, 'slug');

            if (! is_string($slug) || $slug === '') {
                continue;
            }

            $name = self::attr($tag, 'name');
            $parts[] = is_string($name) && $name !== '' ? "`{$slug}` ({$name})" : "`{$slug}`";
        }

        return $parts === [] ? '—' : implode(', ', $parts);
    }

    /**
     * Normalise a value for inline Markdown output.
     */
    public static function orDash(?string $value): string
    {
        $value = $value !== null ? trim($value) : '';

        return $value === '' ? '—' : $value;
    }
}
