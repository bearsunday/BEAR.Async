<?php

declare(strict_types=1);

namespace BEAR\Async\Adapter;

use BEAR\Async\AsyncInterface;
use BEAR\Async\RequestTask;
use Override;

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
    #[Override]
    public function __invoke(array $tasks): void
    {
        foreach ($tasks as $task) {
            if (! ($task instanceof RequestTask)) {
                continue;
            }

            // For crawl: get body and set result
            $result = ($task->getRequest())()->body;
            /** @var array<string, mixed>|null $result */
            $task->setResult($result);
        }
    }

    /** {@inheritDoc} */
    #[Override]
    public function execute(array $requests): array
    {
        $results = [];
        foreach ($requests as $uri => $request) {
            $results[$uri] = (string) $request();
        }

        return $results;
    }

    #[Override]
    public function isAvailable(): bool
    {
        return true;
    }
}
