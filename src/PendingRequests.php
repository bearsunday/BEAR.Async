<?php

declare(strict_types=1);

namespace BEAR\Async;

use BEAR\Resource\ResourceObject;
use BEAR\Async\Exception\ResultNotFoundException;

use function sprintf;

/**
 * Singleton collector for async requests (そうめん流し方式)
 *
 * Collects pending AsyncRequests and executes them all in parallel
 * when any result is requested. Results are cached by request identity
 * (AsyncRequest::hash() — method + URI + links), so two requests to the
 * same URI with different link* configurations are never conflated.
 *
 * Flow:
 * 1. AsyncRequest registers itself via add()
 * 2. Template engine calls __toString() on AsyncRequest
 * 3. getResult() triggers executePending() if result not cached
 * 4. All pending requests execute in parallel
 * 5. Results cached and returned
 *
 * A request invoked directly (`$request()`) reports its ResourceObject via
 * complete(); it leaves the pending batch and its later __toString() renders
 * from that object instead of executing again.
 *
 * If the batch throws, it is abandoned: the pending entries were already
 * consumed, so a later getResult() never replays the batch's side effects.
 * Requests that completed in-process before the failure still resolve via
 * complete(); the rest raise ResultNotFoundException.
 */
final class PendingRequests
{
    /** @var array<string, AsyncRequest> AsyncRequest::hash() => AsyncRequest */
    private array $pending = [];

    /** @var array<string, string> AsyncRequest::hash() => rendered view string */
    private array $results = [];

    /** @var array<string, ResourceObject> AsyncRequest::hash() => directly invoked ResourceObject, rendered lazily */
    private array $invoked = [];

    public function __construct(
        private readonly AsyncInterface $async,
    ) {
    }

    public function add(AsyncRequest $request): void
    {
        $key = $request->hash();
        if (! isset($this->results[$key]) && ! isset($this->invoked[$key]) && ! isset($this->pending[$key])) {
            $this->pending[$key] = $request;
        }
    }

    /**
     * Re-key a pending request after its identity changes
     *
     * AsyncRequest is registered with its initial hash in {@see add()}. When
     * the inner request's query/links mutate (via withQuery/addQuery/link*)
     * the hash changes too, so the pending entry must move to the new key.
     * Otherwise getResult() (which looks up $request->hash()) would miss.
     */
    public function rekey(string $previousKey, AsyncRequest $request): void
    {
        $newKey = $request->hash();
        if ($previousKey === $newKey) {
            return;
        }

        if (isset($this->pending[$previousKey]) && $this->pending[$previousKey] === $request) {
            unset($this->pending[$previousKey]);
        }

        if (! isset($this->results[$newKey]) && ! isset($this->invoked[$newKey]) && ! isset($this->pending[$newKey])) {
            $this->pending[$newKey] = $request;
        }
    }

    /**
     * Record the ResourceObject of a directly invoked request
     *
     * Called from AsyncRequest::__invoke() so the batch does not execute the
     * request again. An existing batch result wins over the invoked object.
     */
    public function complete(AsyncRequest $request, ResourceObject $ro): void
    {
        $key = $request->hash();
        unset($this->pending[$key]);
        if (isset($this->results[$key])) {
            return;
        }

        $this->invoked[$key] = $ro;
    }

    public function getResult(AsyncRequest $request): string
    {
        $key = $request->hash();
        if (! isset($this->results[$key]) && isset($this->invoked[$key])) {
            $this->results[$key] = (string) $this->invoked[$key];
            unset($this->invoked[$key]);
        }

        if (! isset($this->results[$key])) {
            $this->executePending();
        }

        return $this->results[$key] ?? throw new ResultNotFoundException(sprintf('Result not found for URI: %s', $request->toUri()));
    }

    private function executePending(): void
    {
        if ($this->pending === []) {
            return;
        }

        // Consume the batch before executing: the adapters run each request
        // via AsyncRequest::__invoke(), which re-enters this collector
        // (complete(), nested embeds adding new pending entries, nested
        // renders flushing them), and a failed batch must not be replayed.
        $batch = $this->pending;
        $this->pending = [];

        foreach ($this->async->execute($batch) as $key => $result) {
            $this->results[$key] = $result;
            unset($this->invoked[$key]);
        }
    }

    /**
     * Reset state for next request cycle
     *
     * MUST be called at the start of each HTTP request when using
     * long-running servers (Swoole, RoadRunner, etc.) to prevent
     * stale cached results from being returned.
     */
    public function reset(): void
    {
        $this->pending = [];
        $this->results = [];
        $this->invoked = [];
    }
}
