<?php

declare(strict_types=1);

namespace BEAR\Async\Adapter;

use BEAR\Async\AsyncInterface;
use BEAR\Async\RequestTask;
use Override;
use Throwable;

use function array_keys;

/**
 * Synchronous fallback when no async runtime is available
 *
 * This implementation executes tasks sequentially. It serves as a fallback
 * when no async runtime (Swoole, Amp, parallel) is available, ensuring
 * the crawl functionality works in any environment.
 *
 * Failure semantics match the async adapters: a failing task/request does
 * not abort its siblings — every one runs to completion, then the first
 * Throwable in iteration order is rethrown, preserving its original type.
 */
final class SyncAsync implements AsyncInterface
{
    #[Override]
    public function __invoke(array $tasks): void
    {
        $errors = new TaskErrors();
        foreach ($tasks as $key => $task) {
            if (! ($task instanceof RequestTask)) {
                continue;
            }

            try {
                // For crawl: get body and set result
                $result = ($task->getRequest())()->body;
                /** @var array<string, mixed>|null $result */
                $task->setResult($result);
            } catch (Throwable $e) {
                $errors->add($key, $e);
            }
        }

        $errors->throwFirst(array_keys($tasks));
    }

    /** {@inheritDoc} */
    #[Override]
    public function execute(array $requests): array
    {
        $results = [];
        $errors = new TaskErrors();
        foreach ($requests as $key => $request) {
            try {
                $results[$key] = (string) $request();
            } catch (Throwable $e) {
                $errors->add($key, $e);
            }
        }

        $errors->throwFirst(array_keys($requests));

        return $results;
    }
}
