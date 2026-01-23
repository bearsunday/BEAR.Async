<?php

declare(strict_types=1);

namespace BEAR\Async;

use BEAR\Async\Exception\NotInCoroutineException;
use PDO;
use Ray\Di\ProviderInterface;
use Swoole\Coroutine;

/**
 * Provider that supplies PDO instances from the connection pool
 *
 * This provider retrieves a PDO connection from the pool and automatically
 * returns it when the coroutine ends using Swoole's defer() function.
 *
 * IMPORTANT: This provider must be used within a Swoole coroutine context.
 * Calling get() outside a coroutine will throw a RuntimeException.
 *
 * @implements ProviderInterface<PDO>
 */
final class PdoPoolProvider implements ProviderInterface
{
    public function __construct(
        private readonly PdoPool $pool,
    ) {
    }

    /**
     * Get a PDO instance from the pool
     *
     * The connection is automatically returned to the pool when
     * the coroutine completes via defer().
     *
     * @throws NotInCoroutineException if called outside a Swoole coroutine context
     *
     * @codeCoverageIgnore Requires Swoole coroutine context
     */
    public function get(): PDO
    {
        if (Coroutine::getCid() === -1) {
            throw new NotInCoroutineException();
        }

        $pdo = $this->pool->get();
        Coroutine::defer(fn () => $this->pool->put($pdo));

        return $pdo;
    }
}
