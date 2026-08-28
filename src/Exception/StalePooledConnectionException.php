<?php

declare(strict_types=1);

namespace BEAR\Async\Exception;

/**
 * Exception thrown when a pooled connection is still dead after one reconnect retry
 *
 * Swoole's connection pools (PDOPool, RedisPool) do not validate connections
 * on checkout. When the backing service restarts, fails over, or closes idle
 * connections (e.g. MySQL wait_timeout), the pool can keep handing out dead
 * connections forever. This exception surfaces that condition after a single
 * retry has also failed, so callers know the pool itself needs attention
 * rather than retrying indefinitely. The message names the affected pool;
 * the driver error from the last liveness probe is attached as the previous
 * exception.
 */
final class StalePooledConnectionException extends RuntimeException
{
}
