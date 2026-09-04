<?php

declare(strict_types=1);

namespace BEAR\Async;

use BEAR\Async\Exception\NotInCoroutineException;
use Ray\Di\ProviderInterface;
use Swoole\Coroutine;

/**
 * Provides coroutine-local PendingRequests instance for Swoole
 *
 * In Swoole, multiple coroutines (HTTP requests) run concurrently.
 * Each coroutine needs its own PendingRequests to avoid mixing
 * requests and results between concurrent HTTP requests.
 *
 * @implements ProviderInterface<PendingRequests>
 */
final class SwoolePendingRequestsProvider implements ProviderInterface
{
    private const CONTEXT_KEY = 'bear.async.pending_requests';

    public function __construct(
        private readonly AsyncInterface $async,
    ) {
    }

    /** @throws NotInCoroutineException if called outside a Swoole coroutine context */
    public function get(): PendingRequests
    {
        if (Coroutine::getCid() === -1) {
            throw new NotInCoroutineException();
        }

        /** @var \ArrayObject<string, mixed> $context */
        $context = Coroutine::getContext();

        if (! isset($context[self::CONTEXT_KEY])) {
            $context[self::CONTEXT_KEY] = new PendingRequests($this->async);
        }

        /** @var PendingRequests */
        return $context[self::CONTEXT_KEY];
    }
}
