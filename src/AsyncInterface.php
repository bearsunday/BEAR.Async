<?php

declare(strict_types=1);

namespace BEAR\Async;

/**
 * Interface for async/parallel execution of crawl tasks
 *
 * Implementations include:
 * - SwooleAsync: Uses Swoole coroutines with WaitGroup
 * - AmpAsync: Uses Amp async/await pattern
 * - ParallelAsync: Uses ext-parallel for true parallelism
 * - SyncAsync: Synchronous fallback when no async runtime is available
 */
interface AsyncInterface
{
    /**
     * Execute all tasks in parallel and populate their results
     *
     * @param array<string, CrawlTask> $tasks Tasks keyed by request hash
     */
    public function __invoke(array $tasks): void;

    /**
     * Check if this async implementation is currently available
     *
     * For Swoole: requires extension loaded AND running in coroutine context
     * For Amp: requires Amp\Future class exists
     * For Parallel: requires ext-parallel loaded
     * For Sync: always returns true
     */
    public function isAvailable(): bool;
}
