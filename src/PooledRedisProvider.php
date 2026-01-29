<?php

declare(strict_types=1);

namespace BEAR\Async;

use BEAR\Async\Exception\NotInCoroutineException;
use BEAR\Async\Exception\PoolTimeoutException;
use Ray\Di\ProviderInterface;
use Redis;
use Swoole\Coroutine;
use Swoole\Database\RedisPool;

/**
 * Provider that supplies Redis instances from Swoole's connection pool
 *
 * This provider retrieves a Redis connection from the pool and automatically
 * returns it when the coroutine ends using Swoole's defer() function.
 *
 * IMPORTANT: This provider must be used within a Swoole coroutine context.
 * Calling get() outside a coroutine will throw a NotInCoroutineException.
 *
 * @implements ProviderInterface<Redis>
 */
final class PooledRedisProvider implements ProviderInterface
{
    public function __construct(
        private readonly RedisPool $pool,
    ) {
    }

    /**
     * Get a Redis instance from the pool
     *
     * The connection is automatically returned to the pool when
     * the coroutine completes via defer().
     *
     * @throws NotInCoroutineException if called outside a Swoole coroutine context
     * @throws PoolTimeoutException    if timeout occurs while waiting for a connection
     *
     * @codeCoverageIgnore Requires Swoole coroutine context
     */
    public function get(): Redis
    {
        if (Coroutine::getCid() === -1) {
            throw new NotInCoroutineException();
        }

        $redis = $this->pool->get();

        if ($redis === false) {
            throw new PoolTimeoutException();
        }

        Coroutine::defer(function () use ($redis): void {
            $this->pool->put($redis);
        });

        return $redis;
    }
}
