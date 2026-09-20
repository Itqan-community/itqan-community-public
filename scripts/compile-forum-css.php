<?php

// Diagnostic only: compiles the forum LESS and prints the exception Flarum
// normally swallows. Called by check-flarum-assets.sh when the check fails.

error_reporting(E_ALL & ~E_DEPRECATED & ~E_WARNING);
$root = dirname(__DIR__);
require $root.'/vendor/autoload.php';

$app = Flarum\Foundation\Site::fromPaths([
    'base' => $root,
    'public' => $root.'/public',
    'storage' => $root.'/storage',
])->bootApp();

$css = $app->getContainer()->make('flarum.assets.forum')->makeCss();
$sources = (new ReflectionMethod($css, 'getSources'))->invoke($css);

try {
    $out = (new ReflectionMethod($css, 'compile'))->invoke($css, $sources);
    echo 'compiled '.strlen($out)." bytes from ".count($sources)." sources\n";
} catch (Throwable $e) {
    echo get_class($e).': '.$e->getMessage()."\n";
    exit(1);
}
