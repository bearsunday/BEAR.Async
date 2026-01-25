<?php

declare(strict_types=1);

namespace BEAR\Async\Module;

use BEAR\Async\Mysqli\MysqliBatchExecutor;
use BEAR\Async\Mysqli\MysqliConnectionFactory;
use BEAR\Async\Mysqli\MysqliParamBinder;
use Ray\Di\AbstractModule;
use Ray\Di\Scope;

/**
 * DI module for mysqli async batch query execution
 *
 * This module provides bindings for executing multiple MySQL queries
 * asynchronously using mysqli's native async support.
 *
 * Usage:
 *   class AppModule extends AbstractModule
 *   {
 *       protected function configure(): void
 *       {
 *           $this->install(new MysqliBatchModule(
 *               'localhost',
 *               'user',
 *               'pass',
 *               'database'
 *           ));
 *       }
 *   }
 */
final class MysqliBatchModule extends AbstractModule
{
    /**
     * @param string   $host     MySQL host
     * @param string   $user     Database username
     * @param string   $pass     Database password
     * @param string   $database Database name
     * @param int|null $port     MySQL port (null for default 3306)
     * @param string   $socket   MySQL socket path
     * @param string   $charset  Character set (default: utf8mb4)
     */
    public function __construct(
        private readonly string $host,
        private readonly string $user,
        private readonly string $pass,
        private readonly string $database,
        private readonly int|null $port = null,
        private readonly string $socket = '',
        private readonly string $charset = 'utf8mb4',
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->bind(MysqliConnectionFactory::class)
            ->toInstance(new MysqliConnectionFactory(
                $this->host,
                $this->user,
                $this->pass,
                $this->database,
                $this->port,
                $this->socket,
                $this->charset,
            ));

        $this->bind(MysqliParamBinder::class)->in(Scope::SINGLETON);
        $this->bind(MysqliBatchExecutor::class)->in(Scope::SINGLETON);
    }
}
