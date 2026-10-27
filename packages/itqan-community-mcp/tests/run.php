<?php

declare(strict_types=1);

/*
 * Dependency-free test runner.
 *
 * PHPUnit is not part of the Flarum production install (and is not present in
 * the local container), so this runner executes the exact same Unit and
 * Integration test classes as `vendor/bin/phpunit` would:
 *
 *     php packages/itqan-community-mcp/tests/run.php
 *     php packages/itqan-community-mcp/tests/run.php --suite=unit
 *     php packages/itqan-community-mcp/tests/run.php --suite=integration
 *     php packages/itqan-community-mcp/tests/run.php --filter=Arabic
 *
 * Exit code 0 means every executed test passed (skipped tests are allowed when
 * the environment cannot support them, such as a host without database access).
 */

namespace Itqan\CommunityMcp\Tests;

use Itqan\CommunityMcp\Tests\Support\AssertionFailed;
use Itqan\CommunityMcp\Tests\Support\SkippedTest;
use Throwable;

require __DIR__.'/bootstrap.php';

$options = [
    'suite' => 'all',
    'filter' => null,
    'stopOnFailure' => false,
];

foreach (array_slice($argv, 1) as $argument) {
    if (str_starts_with($argument, '--suite=')) {
        $options['suite'] = strtolower(substr($argument, strlen('--suite=')));
    } elseif (str_starts_with($argument, '--filter=')) {
        $options['filter'] = substr($argument, strlen('--filter='));
    } elseif ($argument === '--stop-on-failure') {
        $options['stopOnFailure'] = true;
    } elseif ($argument === '--help' || $argument === '-h') {
        echo "Usage: php tests/run.php [--suite=all|unit|integration] [--filter=text] [--stop-on-failure]\n";
        exit(0);
    } else {
        fwrite(STDERR, "Unknown option: {$argument}\n");
        exit(2);
    }
}

$suites = [
    'unit' => __DIR__.'/Unit',
    'integration' => __DIR__.'/Integration',
];

if ($options['suite'] === 'all') {
    $selectedSuites = $suites;
} elseif (isset($suites[$options['suite']])) {
    $selectedSuites = [$options['suite'] => $suites[$options['suite']]];
} else {
    fwrite(STDERR, "Unknown suite: {$options['suite']} (expected all, unit or integration)\n");
    exit(2);
}

$namespace = 'Itqan\\CommunityMcp\\Tests\\';

$testFiles = [];

foreach ($selectedSuites as $name => $directory) {
    if (! is_dir($directory)) {
        continue;
    }

    $files = glob($directory.'/*Test.php') ?: [];
    sort($files);

    foreach ($files as $file) {
        $testFiles[] = [$name, $file];
    }
}

if ($testFiles === []) {
    fwrite(STDERR, "No test files found.\n");
    exit(1);
}

$passed = 0;
$failed = 0;
$skipped = 0;
$assertions = 0;
$failures = [];
$startedAt = microtime(true);

foreach ($testFiles as [$suiteName, $file]) {
    $relative = str_replace(__DIR__.DIRECTORY_SEPARATOR, '', $file);
    $class = $namespace.str_replace(['/', '.php'], ['\\', ''], $relative);

    echo "\n".str_repeat('=', 72)."\n";
    echo "[{$suiteName}] {$relative}\n";
    echo str_repeat('=', 72)."\n";

    if (! is_file($file)) {
        continue;
    }

    require_once $file;

    if (! class_exists($class)) {
        echo "  ERROR  could not load {$class}\n";
        $failed++;

        continue;
    }

    $reflection = new \ReflectionClass($class);

    if (! $reflection->isInstantiable()) {
        continue;
    }

    try {
        if ($reflection->hasMethod('setUpBeforeClass')) {
            $class::setUpBeforeClass();
        }
    } catch (SkippedTest $e) {
        echo "  SKIP   class: {$e->getMessage()}\n";
        $skipped++;

        continue;
    } catch (Throwable $e) {
        echo "  ERROR  setUpBeforeClass: ".$e->getMessage()."\n";
        $failed++;

        continue;
    }

    $methods = [];

    foreach ($reflection->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
        if ($method->getDeclaringClass()->getName() === $class && str_starts_with($method->getName(), 'test')) {
            $methods[] = $method->getName();
        }
    }

    sort($methods);

    foreach ($methods as $method) {
        if ($options['filter'] !== null && stripos($class.'::'.$method, $options['filter']) === false) {
            continue;
        }

        $label = $method;

        try {
            /** @var object $test */
            $test = $reflection->newInstance();
            $test->setUp();
            $test->{$method}();
            $test->tearDown();

            $count = method_exists($test, 'getAssertionCount') ? $test->getAssertionCount() : null;

            if ($count !== null) {
                $assertions += $count;
            }

            $passed++;

            echo "  PASS   {$label}".($count !== null ? " ({$count} assertions)" : '')."\n";
        } catch (SkippedTest $e) {
            $skipped++;
            echo "  SKIP   {$label}".($e->getMessage() !== '' ? ': '.$e->getMessage() : '')."\n";
        } catch (AssertionFailed $e) {
            $failed++;
            $failures[] = [$class, $method, $e];
            echo "  FAIL   {$label}\n";
            echo '         '.str_replace("\n", "\n         ", trim($e->getMessage()))."\n";

            if ($options['stopOnFailure']) {
                break 2;
            }
        } catch (Throwable $e) {
            $failed++;
            $failures[] = [$class, $method, $e];
            echo "  ERROR  {$label}\n";
            echo '         '.get_class($e).': '.$e->getMessage()."\n";
            echo '         at '.$e->getFile().':'.$e->getLine()."\n";

            if ($options['stopOnFailure']) {
                break 2;
            }
        }
    }

    try {
        if ($reflection->hasMethod('tearDownAfterClass')) {
            $class::tearDownAfterClass();
        }
    } catch (Throwable $e) {
        echo "  WARN   tearDownAfterClass: ".$e->getMessage()."\n";
    }
}

$duration = round(microtime(true) - $startedAt, 2);

echo "\n".str_repeat('-', 72)."\n";
printf(
    "%d passed, %d failed, %d skipped, %d assertions in %ss\n",
    $passed,
    $failed,
    $skipped,
    $assertions,
    $duration
);

if ($failures !== []) {
    echo "\nFailures:\n";

    foreach ($failures as [$class, $method, $e]) {
        echo " - {$class}::{$method}\n";
        echo '   '.str_replace("\n", "\n   ", trim($e->getMessage()))."\n";
    }
}

exit($failed === 0 ? 0 : 1);
