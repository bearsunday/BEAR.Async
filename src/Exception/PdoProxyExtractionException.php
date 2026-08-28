<?php

declare(strict_types=1);

namespace BEAR\Async\Exception;

/**
 * Exception thrown when the underlying PDO instance cannot be extracted from Swoole's PDOProxy
 *
 * Swoole exposes the real PDO through a private `__object` property. If that
 * shape ever changes (e.g. across a major Swoole upgrade), reflection access
 * fails — this exception surfaces that as a domain error rather than leaking
 * the underlying ReflectionException.
 */
final class PdoProxyExtractionException extends RuntimeException
{
}
