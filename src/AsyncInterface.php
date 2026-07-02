<?php

declare(strict_types=1);

namespace BEAR\Async;

/**
 * Interface for async/parallel execution of request tasks
 *
 * Implementations include:
 * - SwooleAsync: Uses Swoole coroutines with WaitGroup
 * - ParallelAsync: Uses ext-parallel for thread pool execution
 * - SyncAsync: Synchronous fallback when no async runtime is available
 */
interface AsyncInterface
{
    /**
     * Execute all tasks in parallel and populate their results
     *
     * For RequestTask (crawl): executes request, sets body via setResult()
     *
     * @param array<string, RequestTask> $tasks Tasks keyed by request hash
     */
    public function __invoke(array $tasks): void;

    /**
     * Execute all AsyncRequests in parallel and return rendered views
     *
     * Used by PendingRequests to execute pending embed requests.
     * Each request is invoked and rendered to string in parallel.
     *
     * Requests are keyed by an opaque per-request key (AbstractRequest::hash()).
     * Implementations MUST key the returned views by the same input keys.
     *
     * @param array<string, AsyncRequest> $requests Requests keyed by request hash
     *
     * @return array<string, string> Rendered views keyed by the same request hash
     */
    public function execute(array $requests): array;
}
