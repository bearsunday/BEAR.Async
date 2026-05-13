<?php

declare(strict_types=1);

namespace BEAR\Async\Module;

use Aura\Sql\DecoratedPdo;
use Aura\Sql\ExtendedPdoInterface;
use BEAR\Async\Exception\NotInCoroutineException;
use BEAR\Async\Exception\PdoProxyExtractionException;
use BEAR\Async\Exception\PoolTimeoutException;
use BEAR\Async\PdoProxyExtractor;
use PDO;
use Ray\Di\ProviderInterface;
use Swoole\Coroutine;
use Swoole\Database\PDOPool;
use Swoole\Database\PDOProxy;

use function assert;

/**
 * Provider that supplies ExtendedPdoInterface instances from the connection pool
 *
 * This provider retrieves a PDO connection from the pool, wraps it with
 * DecoratedPdo for ExtendedPdoInterface compatibility, and automatically
 * returns it when the coroutine ends using Swoole's defer() function.
 *
 * IMPORTANT: This provider must be used within a Swoole coroutine context.
 *
 * @implements ProviderInterface<ExtendedPdoInterface>
 */
final class PooledExtendedPdoProvider implements ProviderInterface
{
    private const CONTEXT_PROXY = 'bear.async.pdo_pool.proxy';
    private const CONTEXT_PDO = 'bear.async.pdo_pool.pdo';
    private const CONTEXT_EXTENDED_PDO = 'bear.async.pdo_pool.extended_pdo';

    public function __construct(
        private readonly PDOPool $pool,
    ) {
    }

    /**
     * Get an ExtendedPdoInterface instance from the pool
     *
     * The connection is automatically returned to the pool when
     * the coroutine completes via defer().
     *
     * @throws NotInCoroutineException     if called outside a Swoole coroutine context
     * @throws PoolTimeoutException        if timeout occurs while waiting for a connection
     * @throws PdoProxyExtractionException if the underlying PDO cannot be read from the proxy
     */
    public function get(): ExtendedPdoInterface
    {
        if (Coroutine::getCid() === -1) {
            throw new NotInCoroutineException();
        }

        /** @var \ArrayObject<string, mixed> $context */
        $context = Coroutine::getContext();
        $extendedPdo = $context[self::CONTEXT_EXTENDED_PDO] ?? null;
        if ($extendedPdo instanceof ExtendedPdoInterface) {
            return $extendedPdo;
        }

        $pdo = $context[self::CONTEXT_PDO] ?? null;
        if (! $pdo instanceof PDO) {
            $proxy = $this->pool->get();
            if ($proxy === false) {
                throw new PoolTimeoutException();
            }

            assert($proxy instanceof PDOProxy);
            $context[self::CONTEXT_PROXY] = $proxy;
            $pdo = PdoProxyExtractor::extract($proxy);
            $context[self::CONTEXT_PDO] = $pdo;

            Coroutine::defer(function () use ($context, $proxy): void {
                unset(
                    $context[self::CONTEXT_PROXY],
                    $context[self::CONTEXT_PDO],
                    $context[self::CONTEXT_EXTENDED_PDO],
                );
                $this->pool->put($proxy);
            });
        }

        $context[self::CONTEXT_EXTENDED_PDO] = new DecoratedPdo($pdo);

        /** @var ExtendedPdoInterface */
        return $context[self::CONTEXT_EXTENDED_PDO];
    }
}
