<?php

declare(strict_types=1);

namespace BEAR\Async\Module;

use BEAR\Async\PooledRedisProvider;
use BEAR\Async\RedisPoolProvider;
use Ray\Di\AbstractModule;
use Ray\Di\Scope;
use Redis;
use Swoole\Database\RedisPool;

/**
 * Redis connection pool module using Swoole's built-in RedisPool
 *
 * This module provides a connection pool for Redis instances in Swoole
 * coroutine environments.
 *
 * Usage (from a swoole context module):
 *   final class SwooleModule extends AbstractModule
 *   {
 *       protected function configure(): void
 *       {
 *           $this->install(new AsyncSwooleModule());
 *           $this->install(new RedisPoolModule(
 *               host: '127.0.0.1',
 *               port: 6379,
 *               poolSize: 64
 *           ));
 *       }
 *   }
 */
final class RedisPoolModule extends AbstractModule
{
    /**
     * @param string       $host          Redis host
     * @param int          $port          Redis port
     * @param string       $auth          Redis password (optional)
     * @param int          $dbIndex       Redis database index
     * @param positive-int $poolSize      Pool size (number of connections)
     * @param float        $borrowTimeout Seconds to wait for a pooled connection before giving up
     */
    public function __construct(
        private readonly string $host = '127.0.0.1',
        private readonly int $port = 6379,
        private readonly string $auth = '',
        private readonly int $dbIndex = 0,
        private readonly int $poolSize = 64,
        private readonly float $borrowTimeout = 5.0,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        // Bind connection parameters for RedisPoolProvider
        $this->bind()->annotatedWith('redis_pool_host')->toInstance($this->host);
        $this->bind()->annotatedWith('redis_pool_port')->toInstance($this->port);
        $this->bind()->annotatedWith('redis_pool_auth')->toInstance($this->auth);
        $this->bind()->annotatedWith('redis_pool_db_index')->toInstance($this->dbIndex);
        $this->bind()->annotatedWith('redis_pool_size')->toInstance($this->poolSize);
        $this->bind()->annotatedWith('redis_pool_borrow_timeout')->toInstance($this->borrowTimeout);

        // Swoole\Database\RedisPool is created at runtime by provider
        $this->bind(RedisPool::class)->toProvider(RedisPoolProvider::class)->in(Scope::SINGLETON);
        $this->bind(Redis::class)->toProvider(PooledRedisProvider::class);
    }
}
