<?php

declare(strict_types=1);

namespace BEAR\Async\Adapter;

use BEAR\Async\AsyncInterface;

/**
 * Synchronous fallback when no async runtime is available
 *
 * This implementation executes tasks sequentially. It serves as a fallback
 * when no async runtime (Swoole, Amp, parallel) is available, ensuring
 * the crawl functionality works in any environment.
 */
final class SyncAsync implements AsyncInterface
{
    public function __invoke(array $tasks): void
    {
        foreach ($tasks as $task) {
            $result = ($task->getRequest())()->body;
            /** @var array<string, mixed>|null $result */
            $task->setResult($result);
        }
    }

    public function isAvailable(): bool
    {
        return true;
    }
}
