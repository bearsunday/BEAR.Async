<?php

declare(strict_types=1);

namespace BEAR\Async;

use Ray\Di\Di\Named;
use Ray\Di\ProviderInterface;

/**
 * Provider for PdoPool that creates the pool at runtime
 *
 * This avoids serialization issues with Swoole\Lock during DI compilation.
 *
 * @implements ProviderInterface<PdoPool>
 */
final class PdoPoolProvider implements ProviderInterface
{
    /**
     * @param non-empty-string $dsn      PDO DSN string
     * @param string           $user     Database username
     * @param string           $pass     Database password
     * @param positive-int     $poolSize Pool size (number of connections)
     */
    public function __construct(
        #[Named('pdo_pool_dsn')] private readonly string $dsn,
        #[Named('pdo_pool_user')] private readonly string $user,
        #[Named('pdo_pool_pass')] private readonly string $pass,
        #[Named('pdo_pool_size')] private readonly int $poolSize,
    ) {
    }

    public function get(): PdoPool
    {
        return new PdoPool($this->dsn, $this->user, $this->pass, $this->poolSize);
    }
}
