<?php

declare(strict_types=1);

namespace BEAR\Async\Module;

use BEAR\Async\Mysqli\MysqliConnectionFactory;
use BEAR\Async\Mysqli\MysqliBatchExecutor;
use BEAR\Async\Mysqli\MysqliParamBinder;
use BEAR\Async\SqlBatchExecutorInterface;
use Ray\Di\AbstractModule;
use Ray\Di\Scope;

use function getenv;
use function sprintf;

/**
 * DI module for mysqli configuration via environment variables
 *
 * Environment variables:
 *   - MYSQLI_HOST: MySQL host (required)
 *   - MYSQLI_USER: Database username (required)
 *   - MYSQLI_PASSWORD: Database password (required)
 *   - MYSQLI_DATABASE: Database name (required)
 *   - MYSQLI_PORT: MySQL port (optional, default: 3306)
 *   - MYSQLI_SOCKET: MySQL socket path (optional)
 *   - MYSQLI_CHARSET: Character set (optional, default: utf8mb4)
 *
 * Usage:
 *   $this->install(new MysqliEnvModule(
 *       'MYSQLI_HOST',
 *       'MYSQLI_USER',
 *       'MYSQLI_PASSWORD',
 *       'MYSQLI_DATABASE',
 *   ));
 */
final class MysqliEnvModule extends AbstractModule
{
    public function __construct(
        private readonly string $hostEnv,
        private readonly string $userEnv,
        private readonly string $passwordEnv,
        private readonly string $databaseEnv,
        private readonly string $portEnv = '',
        private readonly string $socketEnv = '',
        private readonly string $charsetEnv = '',
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $host = $this->getRequiredEnv($this->hostEnv);
        $user = $this->getRequiredEnv($this->userEnv);
        $password = $this->getRequiredEnv($this->passwordEnv);
        $database = $this->getRequiredEnv($this->databaseEnv);

        $port = $this->portEnv !== '' ? (int) getenv($this->portEnv) : null;
        $socket = $this->socketEnv !== '' ? (string) getenv($this->socketEnv) : '';
        $charset = $this->charsetEnv !== '' ? (string) getenv($this->charsetEnv) : 'utf8mb4';

        $this->bind(MysqliConnectionFactory::class)
            ->toInstance(new MysqliConnectionFactory(
                $host,
                $user,
                $password,
                $database,
                $port,
                $socket,
                $charset,
            ));

        $this->bind(MysqliParamBinder::class)->in(Scope::SINGLETON);
        $this->bind(MysqliBatchExecutor::class)->in(Scope::SINGLETON);
        $this->bind(SqlBatchExecutorInterface::class)->to(MysqliBatchExecutor::class)->in(Scope::SINGLETON);
    }

    /**
     * @throws \BEAR\Async\Exception\MissingEnvException
     */
    private function getRequiredEnv(string $name): string
    {
        $value = getenv($name);
        if ($value === false) {
            throw new \BEAR\Async\Exception\MissingEnvException(
                sprintf('Required environment variable "%s" is not set', $name),
            );
        }

        return $value;
    }
}
