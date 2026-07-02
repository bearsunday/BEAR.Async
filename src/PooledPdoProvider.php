<?php

declare(strict_types=1);

namespace BEAR\Async;

use BEAR\Async\Exception\NotInCoroutineException;
use BEAR\Async\Exception\PdoProxyExtractionException;
use BEAR\Async\Exception\PoolTimeoutException;
use BEAR\Async\Exception\StalePooledConnectionException;
use PDO;
use Ray\Di\Di\Named;
use Ray\Di\ProviderInterface;
use Swoole\Database\PDOPool;

/**
 * Provider that supplies PDO instances from Swoole's connection pool
 *
 * This provider retrieves a PDO connection from the pool and automatically
 * returns it when the coroutine ends using Swoole's defer() function.
 *
 * IMPORTANT: This provider must be used within a Swoole coroutine context.
 * Calling get() outside a coroutine will throw a NotInCoroutineException.
 *
 * @implements ProviderInterface<PDO>
 */
final class PooledPdoProvider implements ProviderInterface
{
    private readonly PooledPdoBorrower $borrower;

    public function __construct(
        PDOPool $pool,
        #[Named('pdo_pool_borrow_timeout')] float $borrowTimeout,
    ) {
        $this->borrower = new PooledPdoBorrower($pool, $borrowTimeout);
    }

    /**
     * Get a PDO instance from the pool
     *
     * The connection is automatically returned to the pool when
     * the coroutine completes via defer().
     *
     * @throws NotInCoroutineException        if called outside a Swoole coroutine context
     * @throws PoolTimeoutException           if timeout occurs while waiting for a connection
     * @throws PdoProxyExtractionException    if the underlying PDO cannot be read from the proxy
     * @throws StalePooledConnectionException if the pool keeps handing out dead connections
     *
     * @codeCoverageIgnore Requires Swoole coroutine context
     */
    public function get(): PDO
    {
        return $this->borrower->borrow();
    }
}
