<?php

declare(strict_types=1);

namespace BEAR\Async\Module;

use Aura\Sql\ExtendedPdoInterface;
use BEAR\Async\PdoPoolProvider;
use BEAR\Async\PooledPdoProvider;
use PDO;
use Ray\Di\AbstractModule;
use Ray\Di\Scope;
use Swoole\Database\PDOPool;

/**
 * PDO connection pool module using Swoole's built-in PDOPool
 *
 * This module provides a connection pool for PDO instances in Swoole
 * coroutine environments. When multiple coroutines need database access,
 * each gets its own PDO instance from the pool, preventing "Packets out of order" errors.
 *
 * Usage:
 *   class AppModule extends AbstractModule
 *   {
 *       protected function configure(): void
 *       {
 *           $this->install(new PackageModule());
 *           $this->install(new AsyncSwooleModule());
 *           $this->install(new PdoPoolModule(
 *               'mysql:host=localhost;dbname=test',
 *               'user',
 *               'pass',
 *               poolSize: 64
 *           ));
 *       }
 *   }
 */
final class PdoPoolModule extends AbstractModule
{
    /**
     * @param non-empty-string $dsn      PDO DSN string
     * @param string           $user     Database username
     * @param string           $pass     Database password
     * @param positive-int     $poolSize Pool size (number of connections)
     */
    public function __construct(
        private readonly string $dsn,
        private readonly string $user,
        private readonly string $pass,
        private readonly int $poolSize = 64,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        // Bind connection parameters for PdoPoolProvider
        $this->bind()->annotatedWith('pdo_pool_dsn')->toInstance($this->dsn);
        $this->bind()->annotatedWith('pdo_pool_user')->toInstance($this->user);
        $this->bind()->annotatedWith('pdo_pool_pass')->toInstance($this->pass);
        $this->bind()->annotatedWith('pdo_pool_size')->toInstance($this->poolSize);

        // Swoole\Database\PDOPool is created at runtime by provider
        $this->bind(PDOPool::class)->toProvider(PdoPoolProvider::class)->in(Scope::SINGLETON);
        $this->bind(PDO::class)->toProvider(PooledPdoProvider::class);
        $this->bind(ExtendedPdoInterface::class)->toProvider(PooledExtendedPdoProvider::class);
    }
}
