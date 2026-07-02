<?php

declare(strict_types=1);

namespace BEAR\Async;

use ArrayObject;
use BEAR\Async\Exception\NotInCoroutineException;
use BEAR\Async\Exception\PoolTimeoutException;
use Ray\Di\Di\Named;
use Ray\Di\ProviderInterface;
use Redis;
use Swoole\Coroutine;
use Swoole\Database\RedisPool;

use function sprintf;

/**
 * Provider that supplies Redis instances from Swoole's connection pool
 *
 * This provider retrieves a Redis connection from the pool and automatically
 * returns it when the coroutine ends using Swoole's defer() function. The
 * connection is cached for the lifetime of the coroutine so repeated
 * injections within the same coroutine reuse the same checkout instead of
 * exhausting the pool.
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

        /** @var ArrayObject<string, mixed> $context */
        $context = Coroutine::getContext();
        if (isset($context[self::CONTEXT_REDIS]) && $context[self::CONTEXT_REDIS] instanceof Redis) {
            /** @var Redis */
            return $context[self::CONTEXT_REDIS];
        }

        $redis = $this->pool->get($this->borrowTimeout);
        if ($redis === false) {
            throw new PoolTimeoutException(sprintf(
                'Redis pool exhausted: no connection within %.1fs',
                $this->borrowTimeout,
            ));
        }

        $context[self::CONTEXT_REDIS] = $redis;

        $pool = $this->pool;
        Coroutine::defer(static function () use ($context, $redis, $pool): void {
            unset($context[PooledRedisProvider::CONTEXT_REDIS]);
            $pool->put($redis);
        });

        return $redis;
    }
}
