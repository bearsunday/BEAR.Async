<?php

declare(strict_types=1);

namespace BEAR\Async\Module;

use Aura\Sql\DecoratedPdo;
use Aura\Sql\ExtendedPdoInterface;
use BEAR\Async\Exception\NotInCoroutineException;
use BEAR\Async\Exception\PdoProxyExtractionException;
use BEAR\Async\Exception\PoolTimeoutException;
use BEAR\Async\Exception\StalePooledConnectionException;
use BEAR\Async\PooledPdoBorrower;
use Ray\Di\Di\Named;
use Ray\Di\ProviderInterface;
use Swoole\Coroutine;
use Swoole\Database\PDOPool;

/**
 * Provider that supplies ExtendedPdoInterface instances from the connection pool
 *
 * This provider retrieves a PDO connection from the pool (or reuses one
 * already checked out for this coroutine by PooledPdoProvider/this provider),
 * wraps it with DecoratedPdo for ExtendedPdoInterface compatibility, and
 * automatically returns it when the coroutine ends using Swoole's defer()
 * function.
 *
 * IMPORTANT: This provider must be used within a Swoole coroutine context.
 *
 * @implements ProviderInterface<ExtendedPdoInterface>
 */
final class PooledExtendedPdoProvider implements ProviderInterface
{
    private readonly PooledPdoBorrower $borrower;

    public function __construct(
        PDOPool $pool,
        #[Named('pdo_pool_borrow_timeout')] float $borrowTimeout,
    ) {
        $this->borrower = new PooledPdoBorrower($pool, $borrowTimeout);
    }

    /**
     * Get an ExtendedPdoInterface instance from the pool
     *
     * The connection is automatically returned to the pool when
     * the coroutine completes via defer().
     *
     * @throws NotInCoroutineException        if called outside a Swoole coroutine context
     * @throws PoolTimeoutException           if timeout occurs while waiting for a connection
     * @throws PdoProxyExtractionException    if the underlying PDO cannot be read from the proxy
     * @throws StalePooledConnectionException if the pool keeps handing out dead connections
     */
    public function get(): ExtendedPdoInterface
    {
        if (Coroutine::getCid() === -1) {
            throw new NotInCoroutineException();
        }

        /** @var \ArrayObject<string, mixed> $context */
        $context = Coroutine::getContext();
        $extendedPdo = $context[PooledPdoBorrower::CONTEXT_EXTENDED_PDO] ?? null;
        if ($extendedPdo instanceof ExtendedPdoInterface) {
            return $extendedPdo;
        }

        $pdo = $this->borrower->borrow();
        $context[PooledPdoBorrower::CONTEXT_EXTENDED_PDO] = new DecoratedPdo($pdo);

        /** @var ExtendedPdoInterface */
        return $context[PooledPdoBorrower::CONTEXT_EXTENDED_PDO];
    }
}
