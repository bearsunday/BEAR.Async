<?php

declare(strict_types=1);

namespace BEAR\Async\Fake;

use BEAR\Resource\AbstractRequest;
use BEAR\Resource\InvokerInterface;
use BEAR\Resource\ResourceObject;
use Throwable;

/**
 * Invoker that throws a given Throwable instead of returning a ResourceObject
 *
 * Used to simulate an embed request that fails, so tests can verify that
 * sibling tasks/requests still complete and that the original exception
 * propagates out of the async adapter unchanged.
 */
final class FakeSwooleThrowingInvoker implements InvokerInterface
{
    public int $invokeCount = 0;

    public function __construct(
        private readonly Throwable $throwable,
    ) {
    }

    public function invoke(AbstractRequest $request): ResourceObject
    {
        unset($request);

        $this->invokeCount++;

        throw $this->throwable;
    }
}
