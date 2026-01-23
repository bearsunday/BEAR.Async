<?php

declare(strict_types=1);

namespace BEAR\Async\Exception;

/**
 * Exception thrown when attempting to return a PDO connection to an uninitialized pool
 */
final class PoolNotInitializedException extends \LogicException
{
}
