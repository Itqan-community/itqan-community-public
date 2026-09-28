<?php

/*
 * Registers the local path packages under packages/ with Composer, offline.
 *
 * Why this exists: composer.lock only lists the path packages that were present
 * when it was last resolved, and adding one to composer.json invalidates the
 * lock's content-hash. That forces a full `composer update`, which needs every
 * configured repository — including Itqan-community/flarum-lang-arabic, which
 * is private and unreachable without an SSH key. So on any machine without
 * that key a new local extension can never be installed the normal way, and the
 * dev container silently runs without it.
 *
 * Rather than hand-editing the four Composer-generated files, this writes only
 * what Composer cannot be asked to do:
 *
 *   - vendor/itqan/flarum-<name>          the symlink for a path repository
 *   - vendor/composer/installed.json      the manifest Flarum's
 *                                         ExtensionManager reads to find
 *                                         extensions
 *
 * and then hands the rest back to Composer with `composer dump-autoload`, which
 * rebuilds autoload.php, autoload_psr4.php, autoload_static.php and
 * installed.php from installed.json without contacting any repository. Editing
 * those by hand is what produced a forum that died on every request before.
 *
 * Every value comes from the package's own composer.json. In particular the
 * name is read, never assembled: Extension::nameToId() destructures
 * explode('/', $name), so a missing or malformed name is a fatal on boot.
 *
 * Idempotent, and never edits composer.json or composer.lock, so a later real
 * `composer update` still reconciles everything.
 *
 * Usage: php .docker/link-local-extensions.php [--quiet]
 */

$base = dirname(__DIR__);
$quiet = in_array('--quiet', $argv, true);

function say(string $message): void
{
    global $quiet;

    if (! $quiet) {
        echo $message, "\n";
    }
}

function fail(string $message): void
{
    fwrite(STDERR, $message."\n");
    exit(1);
}

function readJson(string $file): array
{
    $decoded = json_decode(file_get_contents($file), true);

    if (! is_array($decoded)) {
        fail("Could not read $file: ".json_last_error_msg());
    }

    return $decoded;
}

$vendor = $base.'/vendor';
$composerDir = $vendor.'/composer';

if (! is_dir($composerDir)) {
    fail('No vendor/ directory. Run composer install first.');
}

$packages = glob($base.'/packages/itqan-*', GLOB_ONLYDIR);
sort($packages);

if ($packages === []) {
    say('No local packages found under packages/.');

    exit(0);
}

// --- the symlink and the manifest entry ---------------------------------------

$installedFile = $composerDir.'/installed.json';
$installed = readJson($installedFile);

$list = &$installed['packages'];

$present = [];

foreach ($list as $entry) {
    if (isset($entry['name'])) {
        $present[$entry['name']] = true;
    }
}

$changed = false;

foreach ($packages as $dir) {
    $short = substr(basename($dir), strlen('itqan-'));
    $manifest = $dir.'/composer.json';

    if (! file_exists($manifest)) {
        // A leftover directory, e.g. one left by a branch that is not checked
        // out. There is nothing to register and nothing wrong with that.
        say("  packages/$short: no composer.json, nothing to register");
        continue;
    }

    $conf = readJson($manifest);

    $name = $conf['name'] ?? null;

    if (! is_string($name) || ! str_contains($name, '/')) {
        say("  packages/$short: composer.json has no usable \"name\", skipped");
        continue;
    }

    if (($conf['type'] ?? null) !== 'flarum-extension') {
        say("  $name: not a flarum-extension, skipped");
        continue;
    }

    // The symlink Composer would create for a path repository.
    $linkDir = $vendor.'/'.dirname($name);

    if (! is_dir($linkDir) && ! mkdir($linkDir, 0777, true) && ! is_dir($linkDir)) {
        fail("  could not create $linkDir");
    }

    $link = $linkDir.'/'.basename($name);
    $target = '../../'.substr($dir, strlen($base) + 1);
    // readlink() keeps a trailing slash if the link was made with one, so both
    // sides are normalised before comparing; otherwise every run "relinks".
    $current = is_link($link) ? rtrim(readlink($link), '/') : null;

    if ($current === $target) {
        say("  $name: linked");
    } else {
        if (is_link($link) && $current !== $target) {
            unlink($link);
        }

        if (! is_link($link) && ! symlink($target, $link)) {
            fail("  could not link $link");
        }

        say("  $name: linked into vendor/");
    }

    if (isset($present[$name])) {
        say("  $name: already registered");
        continue;
    }

    $entry = [
        'name' => $name,
        'version' => 'dev-main',
        'version_normalized' => 'dev-main',
        'dist' => [
            'type' => 'path',
            'url' => 'packages/'.basename($dir),
            'reference' => sha1_file($manifest),
        ],
    ];

    foreach ([
        'require', 'require-dev', 'conflict', 'replace', 'provide', 'type',
        'extra', 'autoload', 'autoload-dev', 'scripts', 'license', 'authors',
        'description', 'keywords', 'suggest', 'homepage', 'support',
    ] as $key) {
        if (isset($conf[$key])) {
            $entry[$key] = $conf[$key];
        }
    }

    $entry['installation-source'] = 'dist';
    $entry['transport-options'] = ['relative' => true];
    $entry['install-path'] = '../'.dirname($name).'/'.basename($name);

    $list[] = $entry;
    $present[$name] = true;
    $changed = true;

    say("  $name: registered");
}

if (! $changed) {
    say('Nothing to register.');

    exit(0);
}

$json = json_encode($installed, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

if ($json === false) {
    fail('Could not encode installed.json');
}

file_put_contents($installedFile, $json."\n");

// Let Composer rebuild the autoloader from the manifest we just wrote. This
// reads installed.json only, so it needs no repository access and no lock.
say('Rebuilding the autoloader');

$command = sprintf(
    'cd %s && composer dump-autoload --no-scripts --no-interaction 2>&1',
    escapeshellarg($base)
);

$output = [];
$status = 0;

exec($command, $output, $status);

if ($status !== 0) {
    foreach ($output as $line) {
        fwrite(STDERR, '  '.$line."\n");
    }

    fail('composer dump-autoload failed; the extension will not load.');
}

if (! $quiet) {
    foreach ($output as $line) {
        echo '  '.$line, "\n";
    }
}

say('Local extensions registered.');
