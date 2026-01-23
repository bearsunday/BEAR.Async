<?php

declare(strict_types=1);

namespace BEAR\Async;

use BEAR\Async\Exception\PoolNotInitializedException;
use BEAR\Async\Exception\PoolTimeoutException;
use PDO;
use Swoole\Coroutine\Channel;
use Swoole\Lock;

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
    private const DEFAULT_TIMEOUT = 3.0;

    private Channel|null $pool = null;
    private Lock $lock;
    private bool $initialized = false;

    /**
     * @param non-empty-string $dsn     PDO DSN string
     * @param string           $user    Database username
     * @param string           $pass    Database password
     * @param positive-int     $size    Pool size (number of connections)
     * @param float            $timeout Timeout in seconds for getting a connection (default: 3.0, -1 for unlimited)
     */
    public function __construct(
        private readonly string $dsn,
        private readonly string $user,
        private readonly string $pass,
        private readonly int $size = 64,
        private readonly float $timeout = self::DEFAULT_TIMEOUT,
    ) {
        $this->lock = new Lock(Lock::MUTEX);
    }

    /**
     * Get a PDO instance from the pool
     *
     * This method blocks until a connection becomes available or timeout.
     * The pool is lazy-initialized on first call.
     *
     * @throws PoolTimeoutException if timeout occurs while waiting for a connection
     */
    public function get(): PDO
    {
        if (! $this->initialized) {
            $this->lock->lock();
            try {
                // Double-checked locking: re-check after acquiring lock
                /** @psalm-suppress RedundantCondition */
                /** @phpstan-ignore booleanNot.alwaysTrue */
                if (! $this->initialized) {
                    $this->initialize();
                    $this->initialized = true;
                }
            } finally {
                $this->lock->unlock();
            }
        }

        $pool = $this->pool;
        assert($pool !== null);

        /** @var PDO|false $pdo */
        $pdo = $pool->pop($this->timeout);

        if ($pdo === false) {
            throw new PoolTimeoutException();
        }

        return $pdo;
    }

    /**
     * Return a PDO instance to the pool
     *
     * @throws PoolNotInitializedException if the pool has not been initialized
     */
    public function put(PDO $pdo): void
    {
        if ($this->pool === null) {
            throw new PoolNotInitializedException();
        }

        $this->pool->push($pdo);
    }

    /**
     * Initialize the connection pool
     *
     * This must be called within a Swoole coroutine context.
     * If PDO connection creation fails, partial connections are cleaned up.
     */
    private function initialize(): void
    {
        $channel = new Channel($this->size);
        $connections = [];

        try {
            for ($i = 0; $i < $this->size; $i++) {
                $pdo = new PDO($this->dsn, $this->user, $this->pass);
                $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                $connections[] = $pdo;
            }

            // All connections created successfully, push to channel
            foreach ($connections as $pdo) {
                $channel->push($pdo);
            }

            $this->pool = $channel;
        } catch (\PDOException $e) {
            // Clean up partial connections on failure
            $connections = [];
            $channel->close();

            throw $e;
        }
    }
}
