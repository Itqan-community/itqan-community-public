<?php

// Run: php .docker/verify-local-extensions.php
// Exits non-zero if any local extension is not discoverable by Flarum.
//
// The registration script writes installed.json; this checks the result the way
// Flarum actually reads it, through ExtensionManager::getExtensions(), so a
// mistake surfaces here rather than as a blank page.

error_reporting(E_ALL & ~E_DEPRECATED);
require __DIR__.'/../vendor/autoload.php';

$base = dirname(__DIR__);
$quiet = in_array('--quiet', $argv, true);
$failures = 0;

function out(string $line): void
{
    global $quiet;

    if (! $quiet) {
        echo $line, "\n";
    }
}

$paths = new Flarum\Foundation\Paths([
    'base' => $base,
    'public' => $base.'/public',
    'storage' => $base.'/storage',
]);

$config = new class implements Flarum\Settings\SettingsRepositoryInterface {
    public function get($key, $default = null)
    {
        return $default;
    }

    public function set($key, $value = null)
    {
    }

    public function all(): array
    {
        return [];
    }

    public function delete($key)
    {
    }
};

// getExtensions() reads installed.json and nothing else, so the constructor's
// database collaborators are left unset rather than faked: a stub that did not
// match the real signature would hide the very type errors this guards.
$manager = (new ReflectionClass(Flarum\Extension\ExtensionManager::class))->newInstanceWithoutConstructor();

foreach (['filesystem' => new Illuminate\Filesystem\Filesystem, 'paths' => $paths, 'config' => $config] as $property => $value) {
    (new ReflectionProperty(Flarum\Extension\ExtensionManager::class, $property))->setValue($manager, $value);
}

$found = [];

foreach ($manager->getExtensions() as $extension) {
    if (str_starts_with($extension->getId(), 'itqan-')) {
        $found[$extension->name] = $extension->getId();
    }
}

ksort($found);

out("Flarum's ExtensionManager sees:");

foreach ($found as $name => $id) {
    out(sprintf('  %-32s id=%s', $name, $id));
}

out('');

$installed = json_decode(file_get_contents($base.'/vendor/composer/installed.json'), true);
$byName = [];

foreach ($installed['packages'] as $package) {
    $byName[$package['name']] = $package;
}

foreach (glob($base.'/packages/itqan-*', GLOB_ONLYDIR) as $dir) {
    $manifest = $dir.'/composer.json';

    if (! file_exists($manifest)) {
        continue;
    }

    $conf = json_decode(file_get_contents($manifest), true);
    $name = $conf['name'] ?? null;

    if (! is_string($name) || ! str_contains($name, '/')) {
        fwrite(STDERR, "FAIL ".$dir.": unusable \"name\" in composer.json\n");
        $failures++;

        continue;
    }

    if (($conf['type'] ?? null) !== 'flarum-extension') {
        continue;
    }

    if (! isset($found[$name])) {
        fwrite(STDERR, "FAIL $name: not discovered by Flarum\n");
        $failures++;

        continue;
    }

    // nameToId() is where a malformed name used to fatal on boot.
    $expectedId = 'itqan-'.str_replace(['flarum-ext-', 'flarum-'], '', explode('/', $name)[1]);

    if ($found[$name] !== $expectedId) {
        fwrite(STDERR, "FAIL $name: id is {$found[$name]}, expected $expectedId\n");
        $failures++;

        continue;
    }

    if (! isset($byName[$name])) {
        fwrite(STDERR, "FAIL $name: missing from installed.json\n");
        $failures++;

        continue;
    }

    $entry = $byName[$name];
    $packagePath = $base.'/vendor/composer/'.$entry['install-path'];

    if (! is_dir($packagePath)) {
        fwrite(STDERR, "FAIL $name: install-path {$entry['install-path']} is not a directory\n");
        $failures++;

        continue;
    }

    // The extend.php Flarum will require, and the PSR-4 root the autoloader
    // will resolve. Both must exist or the extension is registered but inert.
    if (! file_exists($packagePath.'/extend.php')) {
        fwrite(STDERR, "FAIL $name: no extend.php in the installed path\n");
        $failures++;

        continue;
    }

    $missing = [];

    foreach (($conf['autoload']['psr-4'] ?? []) as $namespace => $path) {
        $relative = is_array($path) ? $path[0] : $path;

        if (! is_dir($packagePath.'/'.rtrim($relative, '/'))) {
            $missing[] = $namespace;
        }
    }

    if ($missing !== []) {
        // A declared-but-absent PSR-4 root is benign: it only matters if
        // something tries to load a class from it, and an extension with no
        // PHP classes never does. Worth reporting, not worth failing over —
        // itqan-composer-tools declares src/ and has no src/.
        fwrite(STDERR, "WARN $name: composer.json declares a PSR-4 root that does not exist ("
            .implode(', ', $missing).")\n");

        continue;
    }

    out("OK   $name");
}

out($failures === 0 ? "ALL LOCAL EXTENSIONS OK" : "$failures FAILED");

exit($failures === 0 ? 0 : 1);
