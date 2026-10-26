<?php

declare(strict_types=1);

namespace Itqan\CommunityMcp\Support;

use Flarum\Settings\SettingsRepositoryInterface;

/**
 * Minimal in-memory settings repository.
 *
 * It lets the MCP dispatcher, resource and controller objects be instantiated
 * outside a booted Flarum container (unit tests, CLI smoke tests) without
 * pulling in the database-backed repository.
 */
final class ArraySettings implements SettingsRepositoryInterface
{
    /**
     * @var array<string, mixed>
     */
    private array $values;

    /**
     * @param array<string, mixed> $values
     */
    public function __construct(array $values = [])
    {
        $this->values = $values;
    }

    public function all(): array
    {
        return $this->values;
    }

    public function get($key, $default = null)
    {
        return array_key_exists($key, $this->values) ? $this->values[$key] : $default;
    }

    public function set($key, $value)
    {
        $this->values[$key] = $value;
    }

    public function delete($keyLike)
    {
        foreach (array_keys($this->values) as $key) {
            if (fnmatch((string) $keyLike, (string) $key)) {
                unset($this->values[$key]);
            }
        }
    }
}
