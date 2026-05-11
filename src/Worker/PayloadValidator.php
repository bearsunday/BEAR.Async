<?php

declare(strict_types=1);

namespace BEAR\Async\Worker;

use BEAR\Async\Exception\NonCopyablePayloadException;

use function get_debug_type;
use function is_array;
use function is_int;
use function is_scalar;
use function sprintf;
use function str_replace;

/**
 * Validates that a value is safely copyable across ext-parallel thread boundaries.
 *
 * ext-parallel can only marshal scalar / null / array-of-(scalar|null|array) values.
 * Objects, closures, and resources cause silent failures or hard errors at the
 * thread boundary. This validator fails fast with a descriptive path so the
 * problem is attributable rather than mysterious.
 */
final class PayloadValidator
{
    /** @codeCoverageIgnore */
    private function __construct()
    {
    }

    /** @throws NonCopyablePayloadException when $value contains a non-copyable element. */
    public static function assertCopyable(mixed $value, string $path = '$'): void
    {
        if ($value === null || is_scalar($value)) {
            return;
        }

        if (is_array($value)) {
            /** @var mixed $element */
            foreach ($value as $key => $element) {
                self::assertCopyable($element, $path . '[' . self::formatKey($key) . ']');
            }

            return;
        }

        throw new NonCopyablePayloadException(sprintf(
            'Non-copyable value at %s: %s. ext-parallel requires scalar/null/array-only payloads.',
            $path,
            get_debug_type($value),
        ));
    }

    private static function formatKey(int|string $key): string
    {
        if (is_int($key)) {
            return (string) $key;
        }

        return "'" . str_replace("'", "\\'", $key) . "'";
    }
}
