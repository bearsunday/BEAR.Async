<?php

declare(strict_types=1);

namespace BEAR\Async;

use BEAR\Resource\Request;

/**
 * Collects crawl tasks for batch parallel execution
 *
 * This class deduplicates requests by their hash. When the same resource
 * is requested multiple times (common in nested crawls), we create only
 * one task and register multiple targets that will all receive the result.
 */
final class CrawlBatch
{
    /** @var array<string, CrawlTask> */
    private array $tasks = [];

    /**
     * Add a request to the batch
     *
     * @param Request              $request The resource request to execute
     * @param string               $rel     The relation key for the result
     * @param array<string, mixed> $body    The body array to update with the result
     */
    public function add(Request $request, string $rel, array &$body): void
    {
        $hash = $request->hash();
        if (isset($this->tasks[$hash])) {
            $this->tasks[$hash]->addTarget($body, $rel);

            return;
        }

        $task = new CrawlTask($hash, $request);
        $task->addTarget($body, $rel);
        $this->tasks[$hash] = $task;
    }

    /** @return array<string, CrawlTask> */
    public function getTasks(): array
    {
        return $this->tasks;
    }

    public function isEmpty(): bool
    {
        return $this->tasks === [];
    }
}
