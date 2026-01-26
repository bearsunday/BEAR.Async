<?php

declare(strict_types=1);

namespace BEAR\Async;

use BEAR\Resource\AbstractRequest;
use BEAR\Resource\ResourceObject;
use Stringable;

/**
 * Decorator for AbstractRequest that enables parallel execution
 *
 * When rendered to string, requests the result from PendingRequests which
 * triggers parallel execution of all pending requests at once.
 *
 * This is the "そうめん" (somen) that gets queued in PendingRequests and
 * flows through together when any result is requested.
 */
final class AsyncRequest implements Stringable
{
    public readonly string $uri;

    /** @var array<string, mixed> */
    public readonly array $query;

    public function __construct(
        private readonly AbstractRequest $inner,
        private readonly PendingRequests $pendingRequests,
    ) {
        $this->uri = (string) $inner->resourceObject->uri;
        $this->query = $inner->query;
        $pendingRequests->add($this);
    }

    /** Invoke the inner request and return the ResourceObject */
    public function __invoke(): ResourceObject
    {
        return ($this->inner)();
    }

    public function __toString(): string
    {
        return $this->pendingRequests->getResult($this->uri);
    }
}
