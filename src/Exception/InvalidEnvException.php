<?php

declare(strict_types=1);

namespace BEAR\Async\Exception;

/**
 * Exception thrown when a set environment variable holds an unusable value
 *
 * Pool tuning variables (size, port, borrow timeout) must be positive
 * numbers. An unset or empty variable falls back to its default, but a
 * variable set to garbage or a non-positive number is a deployment mistake;
 * silently substituting the default would mask it. The message carries the
 * offending NAME=value.
 */
final class InvalidEnvException extends RuntimeException
{
}
