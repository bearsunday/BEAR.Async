<?php

declare(strict_types=1);

namespace BEAR\Async;

use BEAR\Async\Exception\NotInCoroutineException;
use BEAR\Async\Exception\PdoProxyExtractionException;
use BEAR\Async\Exception\PoolTimeoutException;
use PDO;
use Ray\Di\ProviderInterface;
use Swoole\Coroutine;
use Swoole\Database\PDOPool;
use Swoole\Database\PDOProxy;

use function assert;

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
    private const CONTEXT_PROXY = 'bear.async.pdo_pool.proxy';
    private const CONTEXT_PDO = 'bear.async.pdo_pool.pdo';
    private const CONTEXT_EXTENDED_PDO = 'bear.async.pdo_pool.extended_pdo';

    public function __construct(
        private readonly PDOPool $pool,
    ) {
    }

    /**
     * Get a PDO instance from the pool
     *
     * The connection is automatically returned to the pool when
     * the coroutine completes via defer().
     *
     * @throws NotInCoroutineException     if called outside a Swoole coroutine context
     * @throws PoolTimeoutException        if timeout occurs while waiting for a connection
     * @throws PdoProxyExtractionException if the underlying PDO cannot be read from the proxy
     *
     * @codeCoverageIgnore Requires Swoole coroutine context
     */
    public function get(): PDO
    {
        if (Coroutine::getCid() === -1) {
            throw new NotInCoroutineException();
        }

        /** @var \ArrayObject<string, mixed> $context */
        $context = Coroutine::getContext();
        if (isset($context[self::CONTEXT_PDO]) && $context[self::CONTEXT_PDO] instanceof PDO) {
            /** @var PDO */
            return $context[self::CONTEXT_PDO];
        }

        $proxy = $this->pool->get();
        if ($proxy === false) {
            throw new PoolTimeoutException();
        }

        assert($proxy instanceof PDOProxy);
        $context[self::CONTEXT_PROXY] = $proxy;
        $context[self::CONTEXT_PDO] = PdoProxyExtractor::extract($proxy);

        Coroutine::defer(function () use ($context, $proxy): void {
            unset(
                $context[self::CONTEXT_PROXY],
                $context[self::CONTEXT_PDO],
                $context[self::CONTEXT_EXTENDED_PDO],
            );
            $this->pool->put($proxy);
        });

        /** @var PDO */
        return $context[self::CONTEXT_PDO];
    }
}
