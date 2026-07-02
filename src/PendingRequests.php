<?php

declare(strict_types=1);

namespace BEAR\Async;

use BEAR\Async\Exception\ResultNotFoundException;

use function sprintf;

/**
 * Singleton collector for async requests (そうめん流し方式)
 *
 * Collects pending AsyncRequests and executes them all in parallel
 * when any result is requested. Results are cached by request identity
 * (AbstractRequest::hash()), which — unlike the URI alone — also accounts
 * for method and links, so two requests to the same URI with different
 * link* configurations are never conflated.
 *
 * Flow:
 * 1. AsyncRequest registers itself via add()
 * 2. Template engine calls __toString() on AsyncRequest
 * 3. getResult() triggers executePending() if result not cached
 * 4. All pending requests execute in parallel
 * 5. Results cached and returned
 */
final class PendingRequests
{
    /** @var array<string, AsyncRequest> AbstractRequest::hash() => AsyncRequest */
    private array $pending = [];

    /** @var array<string, string> AbstractRequest::hash() => rendered view string */
    private array $results = [];

    public function __construct(
        private readonly AsyncInterface $async,
    ) {
    }

    public function add(AsyncRequest $request): void
    {
        $key = $request->hash();
        if (! isset($this->results[$key]) && ! isset($this->pending[$key])) {
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

        if (! isset($this->results[$newKey]) && ! isset($this->pending[$newKey])) {
            $this->pending[$newKey] = $request;
        }
    }

    public function getResult(AsyncRequest $request): string
    {
        $key = $request->hash();
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

        foreach ($this->async->execute($this->pending) as $key => $result) {
            $this->results[$key] = $result;
        }

        $this->pending = [];
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
    }
}
