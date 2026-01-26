<?php

declare(strict_types=1);

namespace BEAR\Async;

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

    public function get(): PendingRequests
    {
        $context = Coroutine::getContext();

        if (! isset($context[self::CONTEXT_KEY])) {
            $context[self::CONTEXT_KEY] = new PendingRequests($this->async);
        }

        /** @var PendingRequests */
        return $context[self::CONTEXT_KEY];
    }
}
