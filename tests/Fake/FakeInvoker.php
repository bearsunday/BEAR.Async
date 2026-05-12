<?php

declare(strict_types=1);

namespace BEAR\Async\Fake;

use BEAR\Resource\AbstractRequest;
use BEAR\Resource\InvokerInterface;
use BEAR\Resource\ResourceObject;
use LogicException;

final class FakeInvoker implements InvokerInterface
{
    public int $invokeCount = 0;

    public function __construct(
        private readonly ResourceObject $resourceObject,
        private readonly bool $allowInvoke = true,
    ) {
    }

    public function invoke(AbstractRequest $request): ResourceObject
    {
        unset($request);

        if (! $this->allowInvoke) {
            throw new LogicException('FakeInvoker was not expected to be invoked.');
        }

        $this->invokeCount++;

        return $this->resourceObject;
    }
}
