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
 * PDO connection pool module configured via environment variables
 *
 * Environment variables:
 *   - PDO_DSN: PDO DSN string (required)
 *   - PDO_USER: Database username (required)
 *   - PDO_PASSWORD: Database password (required)
 *   - PDO_POOL_SIZE: Pool size (optional, default: 64)
 *   - PDO_POOL_BORROW_TIMEOUT: Seconds to wait for a pooled connection (optional, default: 5.0)
 *
 * Unset or empty optional variables fall back to their defaults; a variable
 * set to a non-numeric or non-positive value throws InvalidEnvException at
 * boot (see {@see PoolEnv}).
 *
 * Usage (from a swoole context module; AppModule stays unchanged):
 *   final class SwooleModule extends AbstractModule
 *   {
 *       protected function configure(): void
 *       {
 *           $this->install(new AsyncSwooleModule());
 *           $this->install(new PdoPoolEnvModule(
 *               'PDO_DSN',
 *               'PDO_USER',
 *               'PDO_PASSWORD',
 *           ));
 *       }
 *   }
 */
final class PdoPoolEnvModule extends AbstractModule
{
    public function __construct(
        private readonly string $dsnEnv,
        private readonly string $userEnv,
        private readonly string $passEnv,
        private readonly string $poolSizeEnv = '',
        private readonly int $defaultPoolSize = 64,
        private readonly string $borrowTimeoutEnv = '',
        private readonly float $defaultBorrowTimeout = 5.0,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $dsn = PoolEnv::required($this->dsnEnv);
        $user = PoolEnv::required($this->userEnv);
        $pass = PoolEnv::required($this->passEnv);
        $poolSize = PoolEnv::int($this->poolSizeEnv, $this->defaultPoolSize, 1);
        $borrowTimeout = PoolEnv::float($this->borrowTimeoutEnv, $this->defaultBorrowTimeout);

        $this->bind()->annotatedWith('pdo_pool_dsn')->toInstance($dsn);
        $this->bind()->annotatedWith('pdo_pool_user')->toInstance($user);
        $this->bind()->annotatedWith('pdo_pool_pass')->toInstance($pass);
        $this->bind()->annotatedWith('pdo_pool_size')->toInstance($poolSize);
        $this->bind()->annotatedWith('pdo_pool_borrow_timeout')->toInstance($borrowTimeout);

        $this->bind(PDOPool::class)->toProvider(PdoPoolProvider::class)->in(Scope::SINGLETON);
        $this->bind(PDO::class)->toProvider(PooledPdoProvider::class);
        $this->bind(ExtendedPdoInterface::class)->toProvider(PooledExtendedPdoProvider::class);
    }
}
