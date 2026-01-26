<?php

declare(strict_types=1);

namespace BEAR\Async\Adapter;

use BEAR\Async\AsyncInterface;
use BEAR\Async\EmbedTask;
use BEAR\Async\RequestTask;

/**
 * Synchronous fallback when no async runtime is available
 *
 * This implementation executes tasks sequentially. It serves as a fallback
 * when no async runtime (Swoole, Amp, parallel) is available, ensuring
 * the crawl functionality works in any environment.
 */
final class SyncAsync implements AsyncInterface
{
    /**
     * @codeCoverageIgnore Requires BEAR.Resource integration test
     */
    public function __invoke(array $tasks): void
    {
        foreach ($tasks as $task) {
            if ($task instanceof EmbedTask) {
                // For embed: (string) triggers invoke + render, both cached
                (string) $task->getRequest();

                continue;
            }

            if (! ($task instanceof RequestTask)) {
                continue;
            }

            // For crawl: get body and set result
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
