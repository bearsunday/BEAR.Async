<?php

declare(strict_types=1);

namespace BEAR\Async\Exception;

/**
 * Exception thrown when code that requires a Swoole coroutine context is called outside one
 */
final class NotInCoroutineException extends RuntimeException
{
}
