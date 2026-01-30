<?php

declare(strict_types=1);

namespace BEAR\Async\Module;

use Aura\Sql\DecoratedPdo;
use Aura\Sql\ExtendedPdoInterface;
use BEAR\Async\Exception\NotInCoroutineException;
use BEAR\Async\Exception\PoolTimeoutException;
use PDO;
use Ray\Di\ProviderInterface;
use ReflectionClass;
use Swoole\Coroutine;
use Swoole\Database\PDOPool;
use Swoole\Database\PDOProxy;

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

        $proxy = $this->pool->get();
        if ($proxy === false) {
            throw new PoolTimeoutException();
        }

        Coroutine::defer(function () use ($proxy): void {
            $this->pool->put($proxy);
        });

        // Extract the actual PDO from PDOProxy for DecoratedPdo compatibility
        $pdo = $this->extractPdo($proxy);

        return new DecoratedPdo($pdo);
    }

    /**
     * Extract the actual PDO instance from a PDOProxy
     *
     * PDOProxy uses a private `__object` property to hold the real PDO.
     * This is Swoole's internal implementation detail.
     *
     * @see \Swoole\Database\PDOProxy::$__object
     */
    private function extractPdo(PDOProxy $proxy): PDO
    {
        $reflection = new ReflectionClass($proxy);
        $property = $reflection->getProperty('__object');

        /** @var PDO */
        return $property->getValue($proxy);
    }
}
