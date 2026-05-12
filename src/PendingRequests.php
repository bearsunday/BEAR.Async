<?php

declare(strict_types=1);

namespace BEAR\Async;

use BEAR\Async\Exception\ResultNotFoundException;

use function sprintf;

/**
 * Singleton collector for async requests (そうめん流し方式)
 *
 * Collects pending AsyncRequests and executes them all in parallel
 * when any result is requested. Results are cached by URI.
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
    /** @var array<string, AsyncRequest> URI (from AsyncRequest::toUri()) => AsyncRequest */
    private array $pending = [];

    /** @var array<string, string> URI => rendered view string */
    private array $results = [];

    public function __construct(
        private readonly AsyncInterface $async,
    ) {
    }

    public function add(AsyncRequest $request): void
    {
        $uri = $request->toUri();
        if (! isset($this->results[$uri]) && ! isset($this->pending[$uri])) {
            $this->pending[$uri] = $request;
        }
    }

    /**
     * Re-key a pending request after its URI changes
     *
     * AsyncRequest is registered with its initial URI in {@see add()}. When
     * the inner request's query/links mutate (via withQuery/addQuery/link*)
     * the URI changes too, so the pending entry must move to the new key.
     * Otherwise __toString() (which looks up $this->toUri()) would miss.
     */
    public function rekey(string $previousUri, AsyncRequest $request): void
    {
        $newUri = $request->toUri();
        if ($previousUri === $newUri) {
            return;
        }

        if (isset($this->pending[$previousUri]) && $this->pending[$previousUri] === $request) {
            unset($this->pending[$previousUri]);
        }

        if (! isset($this->results[$newUri]) && ! isset($this->pending[$newUri])) {
            $this->pending[$newUri] = $request;
        }
    }

    public function getResult(string $uri): string
    {
        if (! isset($this->results[$uri])) {
            $this->executePending();
        }

        return $this->results[$uri] ?? throw new ResultNotFoundException(sprintf('Result not found for URI: %s', $uri));
    }

    private function executePending(): void
    {
        if ($this->pending === []) {
            return;
        }

        foreach ($this->async->execute($this->pending) as $uri => $result) {
            $this->results[$uri] = $result;
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
