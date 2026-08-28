<?php

declare(strict_types=1);

namespace BEAR\Async;

use ArrayObject;
use BEAR\Async\Exception\NotInCoroutineException;
use BEAR\Async\Exception\PoolTimeoutException;
use BEAR\Async\Exception\StalePooledConnectionException;
use Ray\Di\Di\Named;
use Ray\Di\ProviderInterface;
use Redis;
use RedisException;
use Swoole\Coroutine;
use Swoole\Database\RedisPool;
use Throwable;

use function sprintf;

/**
 * Provider that supplies Redis instances from Swoole's connection pool
 *
 * This provider retrieves a Redis connection from the pool and automatically
 * returns it when the coroutine ends using Swoole's defer() function. The
 * connection is cached for the lifetime of the coroutine so repeated
 * injections within the same coroutine reuse the same checkout instead of
 * exhausting the pool. Every checkout is pinged first; a dead connection
 * (e.g. after a Redis restart or idle timeout) is discarded and checkout is
 * retried once, mirroring PooledPdoBorrower.
 *
 * IMPORTANT: This provider must be used within a Swoole coroutine context.
 * Calling get() outside a coroutine will throw a NotInCoroutineException.
 *
 * @implements ProviderInterface<Redis>
 */
final class PooledRedisProvider implements ProviderInterface
{
    private const CONTEXT_REDIS = 'bear.async.redis_pool.redis';

    public function __construct(
        private readonly RedisPool $pool,
        #[Named('redis_pool_borrow_timeout')] private readonly float $borrowTimeout,
    ) {
    }

    /**
     * Get a Redis instance from the pool
     *
     * The connection is automatically returned to the pool when
     * the coroutine completes via defer().
     *
     * @throws NotInCoroutineException        if called outside a Swoole coroutine context
     * @throws PoolTimeoutException           if timeout occurs while waiting for a connection
     * @throws StalePooledConnectionException if the pool keeps handing out dead connections
     */
    public function get(): Redis
    {
        if (Coroutine::getCid() === -1) {
            throw new NotInCoroutineException();
        }

        /** @var ArrayObject<string, mixed> $context */
        $context = Coroutine::getContext();
        if (isset($context[self::CONTEXT_REDIS]) && $context[self::CONTEXT_REDIS] instanceof Redis) {
            /** @var Redis */
            return $context[self::CONTEXT_REDIS];
        }

        $redis = $this->checkoutLive();

        $context[self::CONTEXT_REDIS] = $redis;

        $pool = $this->pool;
        Coroutine::defer(static function () use ($context, $redis, $pool): void {
            unset($context[PooledRedisProvider::CONTEXT_REDIS]);
            $pool->put($redis);
        });

        return $redis;
    }

    /**
     * Check out a connection from the pool, retrying once if it is dead
     *
     * @throws PoolTimeoutException
     * @throws StalePooledConnectionException
     */
    private function checkoutLive(): Redis
    {
        $redis = $this->checkoutOnce();
        $error = $this->ping($redis);
        if ($error === null) {
            return $redis;
        }

        // Discard the dead connection: free the slot instead of returning it.
        $this->discard();

        $redis = $this->checkoutOnce();
        $error = $this->ping($redis);
        if ($error === null) {
            return $redis;
        }

        $this->discard();

        throw new StalePooledConnectionException('Redis pool', 0, $error);
    }

    /** @throws PoolTimeoutException */
    private function checkoutOnce(): Redis
    {
        $redis = $this->pool->get($this->borrowTimeout);
        if ($redis === false) {
            throw new PoolTimeoutException(sprintf(
                'Redis pool exhausted: no connection within %.1fs',
                $this->borrowTimeout,
            ));
        }

        /** @var Redis $redis */
        return $redis;
    }

    private function ping(Redis $redis): RedisException|null
    {
        try {
            $redis->ping();

            return null;
        } catch (RedisException $e) {
            return $e;
        }
    }

    /**
     * Free the dead connection's slot
     *
     * ConnectionPool::put(null) decrements the connection count and then
     * synchronously dials a replacement; if that dial fails it throws with
     * the slot already freed. The failure is swallowed here — the next
     * checkout surfaces the connect error itself.
     */
    private function discard(): void
    {
        try {
            $this->pool->put(null);
        } catch (Throwable) {
            // Next checkout reports the failed replacement dial.
        }
    }
}
