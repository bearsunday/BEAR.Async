<?php

declare(strict_types=1);

namespace BEAR\Async;

use Ray\Di\Di\Named;
use Ray\Di\ProviderInterface;
use Swoole\Database\RedisConfig;
use Swoole\Database\RedisPool;

/**
 * Provider for Swoole\Database\RedisPool
 *
 * Creates a RedisPool using Swoole's built-in connection pool implementation.
 *
 * @implements ProviderInterface<RedisPool>
 */
final class RedisPoolProvider implements ProviderInterface
{
    /**
     * @param string       $host     Redis host
     * @param int          $port     Redis port
     * @param string       $auth     Redis password (optional)
     * @param int          $dbIndex  Redis database index
     * @param positive-int $poolSize Pool size (number of connections)
     */
    public function __construct(
        #[Named('redis_pool_host')] private readonly string $host,
        #[Named('redis_pool_port')] private readonly int $port,
        #[Named('redis_pool_auth')] private readonly string $auth,
        #[Named('redis_pool_db_index')] private readonly int $dbIndex,
        #[Named('redis_pool_size')] private readonly int $poolSize,
    ) {
    }

    public function get(): RedisPool
    {
        $config = (new RedisConfig())
            ->withHost($this->host)
            ->withPort($this->port)
            ->withDbIndex($this->dbIndex);

        if ($this->auth !== '') {
            $config = $config->withAuth($this->auth);
        }

        return new RedisPool($config, $this->poolSize);
    }
}
