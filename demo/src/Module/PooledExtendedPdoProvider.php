<?php

declare(strict_types=1);

namespace BEAR\AsyncDemo\Module;

use Aura\Sql\DecoratedPdo;
use Aura\Sql\ExtendedPdoInterface;
use BEAR\Async\Exception\NotInCoroutineException;
use BEAR\Async\Exception\PoolTimeoutException;
use Ray\Di\ProviderInterface;
use Swoole\Coroutine;
use Swoole\Database\PDOPool;

/**
 * Provider that supplies ExtendedPdoInterface instances from the connection pool
 *
 * This provider retrieves a PDO connection from the pool, wraps it with
 * DecoratedPdo for ExtendedPdoInterface compatibility, and automatically
 * returns it when the coroutine ends using Swoole's defer() function.
 *
 * IMPORTANT: This provider must be used within a Swoole coroutine context.
 *
 * @implements ProviderInterface<ExtendedPdoInterface>
 */
final class PooledExtendedPdoProvider implements ProviderInterface
{
    public function __construct(
        private readonly PDOPool $pool,
    ) {
    }

    /**
     * Get an ExtendedPdoInterface instance from the pool
     *
     * The connection is automatically returned to the pool when
     * the coroutine completes via defer().
     *
     * @throws NotInCoroutineException if called outside a Swoole coroutine context
     * @throws PoolTimeoutException    if timeout occurs while waiting for a connection
     */
    public function get(): ExtendedPdoInterface
    {
        if (Coroutine::getCid() === -1) {
            throw new NotInCoroutineException();
        }

        $pdo = $this->pool->get();
        if ($pdo === false) {
            throw new PoolTimeoutException();
        }

        Coroutine::defer(fn () => $this->pool->put($pdo));

        return new DecoratedPdo($pdo);
    }
}
