<?php

declare(strict_types=1);

namespace BEAR\Async\Module;

use BEAR\Async\PooledRedisProvider;
use BEAR\Async\RedisPoolProvider;
use Ray\Di\AbstractModule;
use Ray\Di\Scope;
use Redis;
use Swoole\Database\RedisPool;

use function getenv;

/**
 * Redis connection pool module configured via environment variables
 *
 * Environment variables:
 *   - REDIS_HOST: Redis host (required)
 *   - REDIS_PORT: Redis port (optional, default: 6379)
 *   - REDIS_PASSWORD: Redis password (optional)
 *   - REDIS_DB_INDEX: Redis database index (optional, default: 0)
 *   - REDIS_POOL_SIZE: Pool size (optional, default: 64)
 *   - REDIS_POOL_BORROW_TIMEOUT: Seconds to wait for a pooled connection (optional, default: 5.0)
 *
 * Unset or empty optional variables fall back to their defaults; a variable
 * set to a non-numeric or out-of-range value throws InvalidEnvException at
 * boot (see {@see PoolEnv}).
 *
 * Usage:
 *   class AppModule extends AbstractModule
 *   {
 *       protected function configure(): void
 *       {
 *           $this->install(new PackageModule());
 *           $this->install(new AsyncSwooleModule());
 *           $this->install(new RedisPoolEnvModule(
 *               'REDIS_HOST',
 *               'REDIS_PORT',
 *               'REDIS_PASSWORD',
 *           ));
 *       }
 *   }
 */
final class RedisPoolEnvModule extends AbstractModule
{
    public function __construct(
        private readonly string $hostEnv,
        private readonly string $portEnv = '',
        private readonly string $authEnv = '',
        private readonly string $dbIndexEnv = '',
        private readonly string $poolSizeEnv = '',
        private readonly int $defaultPort = 6379,
        private readonly int $defaultDbIndex = 0,
        private readonly int $defaultPoolSize = 64,
        private readonly string $borrowTimeoutEnv = '',
        private readonly float $defaultBorrowTimeout = 5.0,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $host = PoolEnv::required($this->hostEnv);
        $port = PoolEnv::int($this->portEnv, $this->defaultPort, 1);
        $auth = $this->authEnv !== '' ? (string) getenv($this->authEnv) : '';
        $dbIndex = PoolEnv::int($this->dbIndexEnv, $this->defaultDbIndex, 0);
        $poolSize = PoolEnv::int($this->poolSizeEnv, $this->defaultPoolSize, 1);
        $borrowTimeout = PoolEnv::float($this->borrowTimeoutEnv, $this->defaultBorrowTimeout);

        $this->bind()->annotatedWith('redis_pool_host')->toInstance($host);
        $this->bind()->annotatedWith('redis_pool_port')->toInstance($port);
        $this->bind()->annotatedWith('redis_pool_auth')->toInstance($auth);
        $this->bind()->annotatedWith('redis_pool_db_index')->toInstance($dbIndex);
        $this->bind()->annotatedWith('redis_pool_size')->toInstance($poolSize);
        $this->bind()->annotatedWith('redis_pool_borrow_timeout')->toInstance($borrowTimeout);

        $this->bind(RedisPool::class)->toProvider(RedisPoolProvider::class)->in(Scope::SINGLETON);
        $this->bind(Redis::class)->toProvider(PooledRedisProvider::class);
    }
}
