<?php

declare(strict_types=1);

namespace Itqan\CommunityMcp\Tests\Support;

use PDO;
use Throwable;

/**
 * Boots the host Flarum application once for the integration suite.
 *
 * The extension is normally enabled in the container, in which case Flarum
 * loads its `extend.php` itself. When the suite runs against a checkout where
 * the extension is not enabled yet, the same extenders are registered manually
 * (the trick `tests/smoke.php` uses) so the routes and middleware still exist.
 */
final class FlarumApp
{
    private static bool $attempted = false;

    private static ?object $app = null;

    private static ?string $error = null;

    /**
     * @return object|null The booted Flarum application, or null with a reason.
     */
    public static function boot(): ?object
    {
        if (self::$attempted) {
            return self::$app;
        }

        self::$attempted = true;

        $root = ITQAN_MCP_TEST_ROOT;

        if (! is_file($root.'/site.php')) {
            self::$error = 'site.php not found at '.$root;

            return null;
        }

        $enabled = self::extensionEnabled($root);

        if ($enabled === null) {
            self::$error = 'database is not reachable from this process (integration tests need the container network)';

            return null;
        }

        try {
            /** @var object $site */
            $site = require $root.'/site.php';

            if (! $enabled) {
                $extend = $root.'/packages/itqan-community-mcp/extend.php';

                if (is_file($extend)) {
                    $site->extendWith(require $extend);
                }
            }

            self::$app = $site->bootApp();
        } catch (Throwable $e) {
            self::$error = get_class($e).': '.$e->getMessage();

            return null;
        }

        return self::$app;
    }

    public static function error(): string
    {
        return self::$error ?? 'unknown error';
    }

    /**
     * `null` means "cannot tell" (no config / database), false means the
     * extension is installed but not enabled.
     */
    private static function extensionEnabled(string $root): ?bool
    {
        $configPath = $root.'/config.php';

        if (! is_file($configPath)) {
            return null;
        }

        try {
            $config = require $configPath;

            if (! is_array($config) || ! isset($config['database']) || ! is_array($config['database'])) {
                return null;
            }

            $database = $config['database'];

            if (($database['driver'] ?? '') !== 'mysql') {
                return null;
            }

            $dsn = sprintf(
                'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
                $database['host'] ?? '127.0.0.1',
                $database['port'] ?? 3306,
                $database['database'] ?? 'flarum'
            );

            $pdo = new PDO($dsn, (string) ($database['username'] ?? ''), (string) ($database['password'] ?? ''), [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_TIMEOUT => 3,
            ]);

            $value = $pdo->query("select value from settings where `key` = 'extensions_enabled'")->fetchColumn();

            return is_string($value) && str_contains($value, 'itqan-community-mcp');
        } catch (Throwable) {
            return null;
        }
    }

    public static function discussionId(): int
    {
        $app = self::boot();

        if ($app === null) {
            return 0;
        }

        try {
            $db = $app->getContainer()->make('db');

            return (int) $db->table('discussions')
                ->whereNull('hidden_at')
                ->orderByDesc('comment_count')
                ->value('id');
        } catch (Throwable) {
            return 0;
        }
    }
}
