<?php

declare(strict_types=1);

namespace BEAR\Async;

use BEAR\Resource\AbstractRequest;

use function count;
use function spl_object_hash;

/**
 * EmbedRequests holds pending embed resource requests during the collection phase
 *
 * Used by AsyncEmbedInterceptor to track all embedded resource requests
 * that will be loaded in parallel by EmbedDataLoader.
 */
final class EmbedRequests
{
    /** @var array<string, FutureResource> */
    private array $futures = [];

    /**
     * Add a request and return a FutureResource for it
     *
     * The request is wrapped in a FutureResource that can be resolved later.
     * The same FutureResource is placed in the resource body for lazy access.
     */
    public function add(AbstractRequest $request): FutureResource
    {
        $id = spl_object_hash($request);
        $future = new FutureResource($id, $request);
        $this->futures[$id] = $future;

        return $future;
    }

    /**
     * Drain all collected futures for loading
     *
     * Returns all pending futures and clears the internal collection.
     *
     * @return array<string, FutureResource>
     */
    public function drain(): array
    {
        $futures = $this->futures;
        $this->futures = [];

        return $futures;
    }

    /** Check if there are pending futures */
    public function hasPending(): bool
    {
        return $this->futures !== [];
    }

    /** Get count of pending futures */
    public function count(): int
    {
        return count($this->futures);
    }
}
