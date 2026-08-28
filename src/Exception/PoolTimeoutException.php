<?php

declare(strict_types=1);

namespace BEAR\Async\Exception;

/**
 * Exception thrown when timeout occurs while waiting for a PDO connection from the pool
 */
final class PoolTimeoutException extends RuntimeException
{
}
