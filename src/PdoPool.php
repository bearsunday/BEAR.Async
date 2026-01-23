<?php

declare(strict_types=1);

namespace BEAR\Async;

use PDO;
use Swoole\Coroutine\Channel;

/**
 * PDO connection pool for Swoole coroutine environments
 *
 * This class manages a pool of PDO connections using Swoole's Channel.
 * When multiple coroutines need database access, each gets its own
 * PDO instance from the pool, preventing "Packets out of order" errors.
 *
 * The pool is lazy-initialized on first get() call to ensure it runs
 * within a Swoole coroutine context.
 *
 * Usage:
 *   $pool = new PdoPool('mysql:host=localhost;dbname=test', 'user', 'pass', 64);
 *   $pdo = $pool->get();
 *   // use $pdo
 *   $pool->put($pdo);
 */
final class PdoPool
{
    private Channel|null $pool = null;

    /**
     * @param non-empty-string $dsn  PDO DSN string
     * @param string           $user Database username
     * @param string           $pass Database password
     * @param positive-int     $size Pool size (number of connections)
     */
    public function __construct(
        private readonly string $dsn,
        private readonly string $user,
        private readonly string $pass,
        private readonly int $size = 64,
    ) {
    }

    /**
     * Get a PDO instance from the pool
     *
     * This method blocks until a connection becomes available.
     * The pool is lazy-initialized on first call.
     */
    public function get(): PDO
    {
        $pool = $this->pool;
        if ($pool === null) {
            $this->initialize();
            $pool = $this->pool;
        }

        assert($pool !== null);

        /** @var PDO */
        return $pool->pop();
    }

    /**
     * Return a PDO instance to the pool
     */
    public function put(PDO $pdo): void
    {
        if ($this->pool === null) {
            return;
        }

        $this->pool->push($pdo);
    }

    /**
     * Initialize the connection pool
     *
     * This must be called within a Swoole coroutine context.
     */
    private function initialize(): void
    {
        $this->pool = new Channel($this->size);
        for ($i = 0; $i < $this->size; $i++) {
            $pdo = new PDO($this->dsn, $this->user, $this->pass);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->pool->push($pdo);
        }
    }
}
