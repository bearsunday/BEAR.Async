<?php

declare(strict_types=1);

namespace BEAR\Async\Module;

use BEAR\Async\Exception\InvalidEnvException;
use BEAR\Async\Exception\MissingEnvException;

use function getenv;
use function is_numeric;
use function sprintf;

/**
 * Environment variable access for the pool modules
 *
 * An unconfigured env name ('') or an unset/empty variable falls back to
 * the given default. A variable that is set but does not parse as a number
 * in the required range throws {@see InvalidEnvException} instead of being
 * silently replaced by the default, so typos like PDO_POOL_SIZE=1O or a
 * -1 "wait forever" convention (deliberately unsupported — bounded waits
 * are the point of the borrow timeout) fail at boot.
 *
 * @internal
 */
final class PoolEnv
{
    /** @throws MissingEnvException */
    public static function required(string $name): string
    {
        $value = getenv($name);
        if ($value === false) {
            throw new MissingEnvException(
                sprintf('Required environment variable "%s" is not set', $name),
            );
        }

        return $value;
    }

    /** @throws InvalidEnvException */
    public static function int(string $name, int $default, int $min): int
    {
        $value = self::raw($name);
        if ($value === null) {
            return $default;
        }

        $int = (int) $value;
        if (! is_numeric($value) || (float) $value !== (float) $int || $int < $min) {
            throw new InvalidEnvException(sprintf('%s=%s', $name, $value));
        }

        return $int;
    }

    /** @throws InvalidEnvException */
    public static function float(string $name, float $default): float
    {
        $value = self::raw($name);
        if ($value === null) {
            return $default;
        }

        if (! is_numeric($value) || (float) $value <= 0) {
            throw new InvalidEnvException(sprintf('%s=%s', $name, $value));
        }

        return (float) $value;
    }

    private static function raw(string $name): string|null
    {
        if ($name === '') {
            return null;
        }

        $value = getenv($name);
        if ($value === false || $value === '') {
            return null;
        }

        return $value;
    }
}
