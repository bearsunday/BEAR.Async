<?php

declare(strict_types=1);

namespace BEAR\Async\Adapter;

use BEAR\Async\AsyncInterface;
use Swoole\Coroutine;
use Swoole\Coroutine\WaitGroup;

use function extension_loaded;

/**
 * Swoole-based async execution using coroutines and WaitGroup
 *
 * This implementation leverages Swoole's coroutine system for parallel
 * execution. All tasks are executed concurrently, and the WaitGroup
 * ensures we wait for all tasks to complete before returning.
 *
 * Note: This only works when running inside a Swoole coroutine context
 * (e.g., inside a Swoole server or Coroutine::create block).
 */
final class SwooleAsync implements AsyncInterface
{
    public function __invoke(array $tasks): void
    {
        $wg = new WaitGroup();

        foreach ($tasks as $task) {
            $wg->add();
            Coroutine::create(function () use ($task, $wg): void {
                try {
                    $result = ($task->getRequest())()->body;
                    /** @var array<string, mixed>|null $result */
                    $task->setResult($result);
                } finally {
                    $wg->done();
                }
            });
        }

        $wg->wait();
    }

    public function isAvailable(): bool
    {
        return extension_loaded('swoole') && Coroutine::getCid() > 0;
    }
}
