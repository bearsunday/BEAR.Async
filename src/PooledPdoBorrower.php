<?php

declare(strict_types=1);

namespace BEAR\Async;

use ArrayObject;
use BEAR\Async\Exception\NotInCoroutineException;
use BEAR\Async\Exception\PdoProxyExtractionException;
use BEAR\Async\Exception\PoolTimeoutException;
use BEAR\Async\Exception\StalePooledConnectionException;
use PDO;
use PDOException;
use Swoole\Coroutine;
use Swoole\Database\PDOPool;
use Swoole\Database\PDOProxy;

use function assert;
use function sprintf;

/**
 * Shared coroutine-scoped PDO checkout logic for pooled PDO providers
 *
 * Both PooledPdoProvider and PooledExtendedPdoProvider need to borrow a PDO
 * from Swoole's PDOPool, cache it for the lifetime of the coroutine, and
 * return it exactly once via a single defer(). This class centralizes that
 * logic, including liveness checking (ping-on-checkout) so a dead connection
 * (e.g. after a MySQL restart or wait_timeout) never poisons the pool.
 *
 * @internal
 */
final class PooledPdoBorrower
{
    public const CONTEXT_PROXY = 'bear.async.pdo_pool.proxy';
    public const CONTEXT_PDO = 'bear.async.pdo_pool.pdo';
    public const CONTEXT_EXTENDED_PDO = 'bear.async.pdo_pool.extended_pdo';

    public function __construct(
        private readonly PDOPool $pool,
        private readonly float $borrowTimeout,
    ) {
    }

    /**
     * Borrow a PDO instance scoped to the current coroutine
     *
     * On a cache hit (a previous call already checked out a connection in
     * this coroutine), the cached PDO is returned and no new defer is
     * registered. On a cache miss, a connection is checked out from the
     * pool, pinged, retried once if dead, and a single defer is registered
     * to return the connection when the coroutine ends.
     *
     * @throws NotInCoroutineException        if called outside a Swoole coroutine context
     * @throws PoolTimeoutException           if timeout occurs while waiting for a connection
     * @throws PdoProxyExtractionException    if the underlying PDO cannot be read from the proxy
     * @throws StalePooledConnectionException if the pool keeps handing out dead connections
     */
    public function borrow(): PDO
    {
        if (Coroutine::getCid() === -1) {
            throw new NotInCoroutineException();
        }

        /** @var ArrayObject<string, mixed> $context */
        $context = Coroutine::getContext();

        if (isset($context[self::CONTEXT_PDO]) && $context[self::CONTEXT_PDO] instanceof PDO) {
            /** @var PDO */
            return $context[self::CONTEXT_PDO];
        }

        [$proxy, $pdo] = $this->checkoutLive();

        $context[self::CONTEXT_PROXY] = $proxy;
        $context[self::CONTEXT_PDO] = $pdo;

        $pool = $this->pool;
        Coroutine::defer(static function () use ($context, $proxy, $pool): void {
            unset(
                $context[PooledPdoBorrower::CONTEXT_PROXY],
                $context[PooledPdoBorrower::CONTEXT_PDO],
                $context[PooledPdoBorrower::CONTEXT_EXTENDED_PDO],
            );
            $pool->put($proxy);
        });

        return $pdo;
    }

    /**
     * Check out a connection from the pool, retrying once if it is dead
     *
     * @return array{0: PDOProxy, 1: PDO}
     *
     * @psalm-suppress UndefinedDocblockClass Swoole stubs are unavailable to static analysis
     *
     * @throws PoolTimeoutException
     * @throws PdoProxyExtractionException
     * @throws StalePooledConnectionException
     */
    private function checkoutLive(): array
    {
        [$proxy, $pdo] = $this->checkoutOnce();
        if ($this->isAlive($pdo)) {
            return [$proxy, $pdo];
        }

        // Discard the dead proxy: free the slot instead of returning a dead connection.
        $this->pool->put(null);

        [$proxy, $pdo] = $this->checkoutOnce();
        if ($this->isAlive($pdo)) {
            return [$proxy, $pdo];
        }

        $this->pool->put(null);

        throw new StalePooledConnectionException(
            'PDO pool exhausted: pooled connections are stale (e.g. the database was restarted)',
        );
    }

    /**
     * @return array{0: PDOProxy, 1: PDO}
     *
     * @psalm-suppress UndefinedDocblockClass Swoole stubs are unavailable to static analysis
     *
     * @throws PoolTimeoutException
     * @throws PdoProxyExtractionException
     */
    private function checkoutOnce(): array
    {
        $proxy = $this->pool->get($this->borrowTimeout);
        if ($proxy === false) {
            throw new PoolTimeoutException(sprintf(
                'PDO pool exhausted: no connection within %.1fs',
                $this->borrowTimeout,
            ));
        }

        assert($proxy instanceof PDOProxy);

        return [$proxy, PdoProxyExtractor::extract($proxy)];
    }

    private function isAlive(PDO $pdo): bool
    {
        try {
            return $pdo->query('SELECT 1') !== false;
        } catch (PDOException) {
            return false;
        }
    }
}
