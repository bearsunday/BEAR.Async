<?php

declare(strict_types=1);

namespace BEAR\AsyncDemo\Module;

use BEAR\Async\Module\AsyncSwooleModule;
use BEAR\Async\Module\PdoPoolModule;
use Ray\Di\AbstractModule;
use Ray\MediaQuery\DbQueryInterceptor;

use function getenv;

/**
 * Swoole context module
 *
 * Install this module by using the "swoole" context (e.g., "prod-swoole-hal-api-app")
 *
 * This module enables parallel execution of #[Embed] resources using
 * Swoole coroutines with connection pooling.
 *
 * Each coroutine gets its own database connection from the pool,
 * avoiding "unbuffered queries" errors from concurrent access.
 *
 * Requires MySQL/PostgreSQL database (connection pooling is not supported with SQLite).
 * Set environment variables: DB_DSN, DB_USER, DB_PASS
 */
final class SwooleModule extends AbstractModule
{
    protected function configure(): void
    {
        $this->install(new AsyncSwooleModule());

        // Connection pool for coroutine-safe database access
        // PdoPoolModule now binds both PDO and ExtendedPdoInterface
        $dsn = getenv('DB_DSN') ?: 'mysql:host=127.0.0.1;dbname=demo';
        $user = getenv('DB_USER') ?: 'demo';
        $pass = getenv('DB_PASS') ?: 'demo';

        $poolSize = (int) (getenv('PDO_POOL_SIZE') ?: 64);
        if ($poolSize < 1) {
            $poolSize = 64;
        }

        $this->install(new PdoPoolModule($dsn, $user, $pass, $poolSize));

        // DbQueryInterceptor must NOT be singleton in Swoole context
        // Each coroutine needs its own interceptor with its own database connection
        $this->bind(DbQueryInterceptor::class);
    }
}
