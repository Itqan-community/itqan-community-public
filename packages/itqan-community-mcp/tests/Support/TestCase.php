<?php

declare(strict_types=1);

namespace Itqan\CommunityMcp\Tests\Support;

/*
 * PHPUnit is an optional development dependency: Flarum's production install
 * does not ship it. The same test classes therefore extend either PHPUnit's
 * TestCase (when available) or a small local equivalent used by
 * `tests/run.php`.
 */
if (class_exists(\PHPUnit\Framework\TestCase::class)) {
    abstract class TestCase extends \PHPUnit\Framework\TestCase
    {
    }
} else {
    abstract class TestCase extends StandaloneTestCase
    {
    }
}
