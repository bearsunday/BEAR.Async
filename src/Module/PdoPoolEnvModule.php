<?php

declare(strict_types=1);

namespace BEAR\Async\Module;

use Aura\Sql\ExtendedPdoInterface;
use BEAR\Async\Exception\MissingEnvException;
use BEAR\Async\PdoPoolProvider;
use BEAR\Async\PooledPdoProvider;
use PDO;
use Ray\Di\AbstractModule;
use Ray\Di\Scope;
use Swoole\Database\PDOPool;

use function getenv;
use function sprintf;

/**
 * PDO connection pool module configured via environment variables
 *
 * Environment variables:
 *   - PDO_DSN: PDO DSN string (required)
 *   - PDO_USER: Database username (required)
 *   - PDO_PASSWORD: Database password (required)
 *   - PDO_POOL_SIZE: Pool size (optional, default: 64)
 *
 * Usage:
 *   class AppModule extends AbstractModule
 *   {
 *       protected function configure(): void
 *       {
 *           $this->install(new PackageModule());
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
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $dsn = $this->getRequiredEnv($this->dsnEnv);
        $user = $this->getRequiredEnv($this->userEnv);
        $pass = $this->getRequiredEnv($this->passEnv);
        $poolSize = $this->poolSizeEnv !== '' ? (int) getenv($this->poolSizeEnv) : $this->defaultPoolSize;

        if ($poolSize <= 0) {
            $poolSize = $this->defaultPoolSize;
        }

        $this->bind()->annotatedWith('pdo_pool_dsn')->toInstance($dsn);
        $this->bind()->annotatedWith('pdo_pool_user')->toInstance($user);
        $this->bind()->annotatedWith('pdo_pool_pass')->toInstance($pass);
        $this->bind()->annotatedWith('pdo_pool_size')->toInstance($poolSize);

        $this->bind(PDOPool::class)->toProvider(PdoPoolProvider::class)->in(Scope::SINGLETON);
        $this->bind(PDO::class)->toProvider(PooledPdoProvider::class);
        $this->bind(ExtendedPdoInterface::class)->toProvider(PooledExtendedPdoProvider::class);
    }

    private function getRequiredEnv(string $name): string
    {
        $value = getenv($name);
        if ($value === false) {
            throw new MissingEnvException(
                sprintf('Required environment variable "%s" is not set', $name),
            );
        }

        return $value;
    }
}
