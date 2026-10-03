<?php

// Run: php packages/itqan-llms/tests/run_all.php
// Runs every test script in this directory and reports one summary.
// The scripts are standalone on purpose — no database, no Flarum boot — so
// they can be run on a laptop and in CI without standing anything up.

error_reporting(E_ALL & ~E_DEPRECATED);

$scripts = glob(__DIR__.'/*_test.php');
sort($scripts);

$failed = [];

foreach ($scripts as $script) {
    $name = basename($script);

    echo "\n".str_repeat('=', 70)."\n";
    echo $name."\n";
    echo str_repeat('=', 70)."\n";

    $output = [];
    $status = 0;

    exec(escapeshellarg(PHP_BINARY).' '.escapeshellarg($script).' 2>&1', $output, $status);

    echo implode("\n", $output)."\n";

    if ($status !== 0) {
        $failed[] = $name;
    }
}

echo "\n".str_repeat('#', 70)."\n";

if ($failed === []) {
    echo 'ALL SUITES PASSED ('.count($scripts)." scripts)\n";

    exit(0);
}

echo count($failed).' SUITE(S) FAILED: '.implode(', ', $failed)."\n";

exit(1);
