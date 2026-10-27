<?php

declare(strict_types=1);

/*
 * Shared bootstrap for PHPUnit and the dependency-free runner (`tests/run.php`).
 *
 * The extension is normally installed as a path repository inside the host
 * Flarum installation, so the host's `vendor/autoload.php` already covers both
 * Flarum and this package. A small PSR-4 fallback keeps the tests runnable from
 * a standalone checkout (or before `composer update` has been run) as long as
 * Flarum itself is installed somewhere above this directory.
 */

$root = dirname(__DIR__, 3);

$autoloadCandidates = [
    $root.'/vendor/autoload.php',
    dirname(__DIR__, 4).'/vendor/autoload.php',
    dirname(__DIR__, 4).'/autoload.php',
];

$autoloaded = false;

foreach ($autoloadCandidates as $candidate) {
    if (is_file($candidate)) {
        require_once $candidate;
        $autoloaded = true;

        break;
    }
}

if (! defined('ITQAN_MCP_TEST_ROOT')) {
    define('ITQAN_MCP_TEST_ROOT', $root);
}

if (! defined('ITQAN_MCP_TEST_AUTOLOADED')) {
    define('ITQAN_MCP_TEST_AUTOLOADED', $autoloaded);
}

spl_autoload_register(static function (string $class): void {
    static $prefixes = null;

    if ($prefixes === null) {
        $prefixes = [
            'Itqan\\CommunityMcp\\Tests\\' => __DIR__.'/',
            'Itqan\\CommunityMcp\\' => dirname(__DIR__).'/src/',
        ];
    }

    foreach ($prefixes as $prefix => $base) {
        if (! str_starts_with($class, $prefix)) {
            continue;
        }

        $path = $base.str_replace('\\', '/', substr($class, strlen($prefix))).'.php';

        if (is_file($path)) {
            require $path;
        }

        return;
    }
});
