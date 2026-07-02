<?php

declare(strict_types=1);

namespace BEAR\Async\Exception;

/**
 * Exception thrown when a pooled PDO connection is still dead after one reconnect retry
 *
 * Swoole's PDOPool does not validate connections on checkout. When the
 * database restarts, fails over, or closes idle connections (MySQL
 * wait_timeout), the pool can keep handing out dead connections forever.
 * This exception surfaces that condition after a single retry has also
 * failed, so callers know the pool itself needs attention rather than
 * retrying indefinitely.
 */
final class StalePooledConnectionException extends RuntimeException
{
}
