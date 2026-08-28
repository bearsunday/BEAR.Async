<?php

declare(strict_types=1);

namespace BEAR\Async\Fake;

use BEAR\Resource\AbstractRequest;
use BEAR\Resource\InvokerInterface;
use BEAR\Resource\ResourceObject;
use Swoole\Coroutine;
use Throwable;

/**
 * Invoker that yields to other coroutines before throwing
 *
 * Makes its coroutine finish LAST so tests can prove the adapter rethrows
 * by task submission order, not coroutine completion order.
 */
final class FakeSwooleDelayedThrowingInvoker implements InvokerInterface
{
    public function __construct(
        private readonly Throwable $throwable,
        private readonly float $delaySeconds,
    ) {
    }

    public function invoke(AbstractRequest $request): ResourceObject
    {
        unset($request);

        Coroutine::sleep($this->delaySeconds);

        throw $this->throwable;
    }
}
