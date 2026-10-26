<?php

declare(strict_types=1);

namespace Itqan\CommunityMcp\Tests\Support;

use RuntimeException;
use Throwable;

/**
 * Thrown by the standalone runner when a test cannot run in this environment
 * (for example an integration test on a host that cannot reach the database).
 */
final class SkippedTest extends RuntimeException
{
}

/**
 * Minimal assertion surface used when PHPUnit is not installed.
 *
 * The test cases themselves only call methods that exist in both PHPUnit and
 * this class, so the exact same files run through `vendor/bin/phpunit` and
 * through the dependency-free `tests/run.php` runner.
 */
abstract class StandaloneTestCase
{
    private int $assertionCount = 0;

    public function setUp(): void
    {
    }

    public function tearDown(): void
    {
    }

    public function getAssertionCount(): int
    {
        return $this->assertionCount;
    }

    public function markTestSkipped(string $message = ''): void
    {
        throw new SkippedTest($message);
    }

    public function markTestIncomplete(string $message = ''): void
    {
        throw new SkippedTest($message);
    }

    public function fail(string $message = ''): void
    {
        throw new AssertionFailed($message !== '' ? $message : 'Test failed.');
    }

    /**
     * @param mixed $expected
     * @param mixed $actual
     */
    public function assertSame($expected, $actual, string $message = ''): void
    {
        $this->assertionCount++;

        if ($expected !== $actual) {
            $this->fail($this->withMessage(
                sprintf('Failed asserting that two values are identical. Expected %s, got %s.', $this->export($expected), $this->export($actual)),
                $message
            ));
        }
    }

    /**
     * @param mixed $expected
     * @param mixed $actual
     */
    public function assertNotSame($expected, $actual, string $message = ''): void
    {
        $this->assertionCount++;

        if ($expected === $actual) {
            $this->fail($this->withMessage('Failed asserting that two values are not identical.', $message));
        }
    }

    /**
     * @param mixed $expected
     * @param mixed $actual
     */
    public function assertEquals($expected, $actual, string $message = ''): void
    {
        $this->assertionCount++;

        if ($expected != $actual) {
            $this->fail($this->withMessage(
                sprintf('Failed asserting that two values are equal. Expected %s, got %s.', $this->export($expected), $this->export($actual)),
                $message
            ));
        }
    }

    /**
     * @param mixed $condition
     */
    public function assertTrue($condition, string $message = ''): void
    {
        $this->assertionCount++;

        if ($condition !== true) {
            $this->fail($this->withMessage('Failed asserting that the value is true: '.$this->export($condition), $message));
        }
    }

    /**
     * @param mixed $condition
     */
    public function assertFalse($condition, string $message = ''): void
    {
        $this->assertionCount++;

        if ($condition !== false) {
            $this->fail($this->withMessage('Failed asserting that the value is false: '.$this->export($condition), $message));
        }
    }

    /**
     * @param mixed $value
     */
    public function assertNull($value, string $message = ''): void
    {
        $this->assertionCount++;

        if ($value !== null) {
            $this->fail($this->withMessage('Failed asserting that the value is null: '.$this->export($value), $message));
        }
    }

    /**
     * @param mixed $value
     */
    public function assertNotNull($value, string $message = ''): void
    {
        $this->assertionCount++;

        if ($value === null) {
            $this->fail($this->withMessage('Failed asserting that the value is not null.', $message));
        }
    }

    /**
     * @param mixed $value
     */
    public function assertEmpty($value, string $message = ''): void
    {
        $this->assertionCount++;

        if (! empty($value)) {
            $this->fail($this->withMessage('Failed asserting that the value is empty: '.$this->export($value), $message));
        }
    }

    /**
     * @param mixed $value
     */
    public function assertNotEmpty($value, string $message = ''): void
    {
        $this->assertionCount++;

        if (empty($value)) {
            $this->fail($this->withMessage('Failed asserting that the value is not empty.', $message));
        }
    }

    /**
     * @param int|array<mixed>|\Countable $haystack
     */
    public function assertCount(int $expected, $haystack, string $message = ''): void
    {
        $this->assertionCount++;

        $actual = is_array($haystack) || $haystack instanceof \Countable
            ? count($haystack)
            : null;

        if ($actual !== $expected) {
            $this->fail($this->withMessage(
                sprintf('Failed asserting that count %s matches expected %d.', $actual === null ? 'is not countable' : (string) $actual, $expected),
                $message
            ));
        }
    }

    /**
     * @param array<mixed>|object $haystack
     * @param mixed $key
     */
    public function assertArrayHasKey($key, $haystack, string $message = ''): void
    {
        $this->assertionCount++;

        $exists = is_array($haystack) ? array_key_exists($key, $haystack) : (is_object($haystack) && isset($haystack->{$key}));

        if (! $exists) {
            $this->fail($this->withMessage('Failed asserting that the array has the key '.$this->export($key).'.', $message));
        }
    }

    /**
     * @param array<mixed>|object $haystack
     * @param mixed $key
     */
    public function assertArrayNotHasKey($key, $haystack, string $message = ''): void
    {
        $this->assertionCount++;

        $exists = is_array($haystack) ? array_key_exists($key, $haystack) : (is_object($haystack) && isset($haystack->{$key}));

        if ($exists) {
            $this->fail($this->withMessage('Failed asserting that the array does not have the key '.$this->export($key).'.', $message));
        }
    }

    public function assertStringContainsString(string $needle, string $haystack, string $message = ''): void
    {
        $this->assertionCount++;

        if (! str_contains($haystack, $needle)) {
            $this->fail($this->withMessage(
                sprintf('Failed asserting that %s contains %s.', $this->export($haystack), $this->export($needle)),
                $message
            ));
        }
    }

    public function assertStringNotContainsString(string $needle, string $haystack, string $message = ''): void
    {
        $this->assertionCount++;

        if (str_contains($haystack, $needle)) {
            $this->fail($this->withMessage(
                sprintf('Failed asserting that %s does not contain %s.', $this->export($haystack), $this->export($needle)),
                $message
            ));
        }
    }

    public function assertStringStartsWith(string $prefix, string $haystack, string $message = ''): void
    {
        $this->assertionCount++;

        if (! str_starts_with($haystack, $prefix)) {
            $this->fail($this->withMessage(
                sprintf('Failed asserting that %s starts with %s.', $this->export($haystack), $this->export($prefix)),
                $message
            ));
        }
    }

    public function assertStringEndsWith(string $suffix, string $haystack, string $message = ''): void
    {
        $this->assertionCount++;

        if (! str_ends_with($haystack, $suffix)) {
            $this->fail($this->withMessage(
                sprintf('Failed asserting that %s ends with %s.', $this->export($haystack), $this->export($suffix)),
                $message
            ));
        }
    }

    /**
     * @param mixed $value
     */
    public function assertIsArray($value, string $message = ''): void
    {
        $this->assertionCount++;

        if (! is_array($value)) {
            $this->fail($this->withMessage('Failed asserting that the value is an array: '.$this->export($value), $message));
        }
    }

    /**
     * @param mixed $value
     */
    public function assertIsString($value, string $message = ''): void
    {
        $this->assertionCount++;

        if (! is_string($value)) {
            $this->fail($this->withMessage('Failed asserting that the value is a string: '.$this->export($value), $message));
        }
    }

    /**
     * @param mixed $value
     */
    public function assertIsInt($value, string $message = ''): void
    {
        $this->assertionCount++;

        if (! is_int($value)) {
            $this->fail($this->withMessage('Failed asserting that the value is an integer: '.$this->export($value), $message));
        }
    }

    /**
     * @param mixed $value
     */
    public function assertIsObject($value, string $message = ''): void
    {
        $this->assertionCount++;

        if (! is_object($value)) {
            $this->fail($this->withMessage('Failed asserting that the value is an object: '.$this->export($value), $message));
        }
    }

    /**
     * @param class-string $class
     * @param mixed $actual
     */
    public function assertInstanceOf(string $class, $actual, string $message = ''): void
    {
        $this->assertionCount++;

        if (! $actual instanceof $class) {
            $this->fail($this->withMessage(
                sprintf('Failed asserting that %s is an instance of %s.', $this->export($actual), $class),
                $message
            ));
        }
    }

    /**
     * @param mixed $actual
     */
    public function assertGreaterThan($expected, $actual, string $message = ''): void
    {
        $this->assertionCount++;

        if (! ($actual > $expected)) {
            $this->fail($this->withMessage(
                sprintf('Failed asserting that %s is greater than %s.', $this->export($actual), $this->export($expected)),
                $message
            ));
        }
    }

    /**
     * @param mixed $actual
     */
    public function assertGreaterThanOrEqual($expected, $actual, string $message = ''): void
    {
        $this->assertionCount++;

        if (! ($actual >= $expected)) {
            $this->fail($this->withMessage(
                sprintf('Failed asserting that %s is greater than or equal to %s.', $this->export($actual), $this->export($expected)),
                $message
            ));
        }
    }

    /**
     * @param mixed $actual
     */
    public function assertLessThan($expected, $actual, string $message = ''): void
    {
        $this->assertionCount++;

        if (! ($actual < $expected)) {
            $this->fail($this->withMessage(
                sprintf('Failed asserting that %s is less than %s.', $this->export($actual), $this->export($expected)),
                $message
            ));
        }
    }

    /**
     * @param mixed $actual
     */
    public function assertLessThanOrEqual($expected, $actual, string $message = ''): void
    {
        $this->assertionCount++;

        if (! ($actual <= $expected)) {
            $this->fail($this->withMessage(
                sprintf('Failed asserting that %s is less than or equal to %s.', $this->export($actual), $this->export($expected)),
                $message
            ));
        }
    }

    /**
     * @param iterable<mixed> $haystack
     * @param mixed $needle
     */
    public function assertContains($needle, iterable $haystack, string $message = ''): void
    {
        $this->assertionCount++;

        foreach ($haystack as $value) {
            if ($value === $needle) {
                return;
            }
        }

        $this->fail($this->withMessage(
            sprintf('Failed asserting that the iterable contains %s.', $this->export($needle)),
            $message
        ));
    }

    /**
     * @param iterable<mixed> $haystack
     * @param mixed $needle
     */
    public function assertNotContains($needle, iterable $haystack, string $message = ''): void
    {
        $this->assertionCount++;

        foreach ($haystack as $value) {
            if ($value === $needle) {
                $this->fail($this->withMessage(
                    sprintf('Failed asserting that the iterable does not contain %s.', $this->export($needle)),
                    $message
                ));
            }
        }
    }

    public function assertMatchesRegularExpression(string $pattern, string $string, string $message = ''): void
    {
        $this->assertionCount++;

        if (preg_match($pattern, $string) !== 1) {
            $this->fail($this->withMessage(
                sprintf('Failed asserting that %s matches %s.', $this->export($string), $pattern),
                $message
            ));
        }
    }

    /**
     * @param mixed $value
     */
    protected function export($value): string
    {
        $exported = var_export($value, true);

        if (strlen($exported) > 1200) {
            $exported = substr($exported, 0, 1200).'…';
        }

        return $exported;
    }

    protected function withMessage(string $assertion, string $message): string
    {
        return $message !== '' ? $message."\n".$assertion : $assertion;
    }
}

/**
 * Assertion failure raised by the standalone runner.
 */
final class AssertionFailed extends RuntimeException
{
}
