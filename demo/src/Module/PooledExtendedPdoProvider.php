<?php

declare(strict_types=1);

namespace BEAR\AsyncDemo\Module;

use Aura\Sql\DecoratedPdo;
use Aura\Sql\ExtendedPdoInterface;
use BEAR\Async\Exception\NotInCoroutineException;
use BEAR\Async\PdoPool;
use Ray\Di\ProviderInterface;
use Swoole\Coroutine;

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
        private readonly PdoPool $pool,
    ) {
    }

    /**
     * Get an ExtendedPdoInterface instance from the pool
     *
     * The connection is automatically returned to the pool when
     * the coroutine completes via defer().
     *
     * @throws NotInCoroutineException if called outside a Swoole coroutine context
     */
    public function get(): ExtendedPdoInterface
    {
        if (Coroutine::getCid() === -1) {
            throw new NotInCoroutineException();
        }

        $pdo = $this->pool->get();
        Coroutine::defer(fn () => $this->pool->put($pdo));

        return new DecoratedPdo($pdo);
    }
}
