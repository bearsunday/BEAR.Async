<?php

declare(strict_types=1);

namespace BEAR\Async\Adapter;

use BEAR\Async\AsyncInterface;
use BEAR\Async\AsyncRequest;
use BEAR\Async\RequestTask;
use Override;
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
 *
 * @codeCoverageIgnore Requires Swoole coroutine context and BEAR.Resource integration
 */
final class SwooleAsync implements AsyncInterface
{
    #[Override]
    public function __invoke(array $tasks): void
    {
        $wg = new WaitGroup();

        foreach ($tasks as $task) {
            $wg->add();
            Coroutine::create(function () use ($task, $wg): void {
                try {
                    if (! ($task instanceof RequestTask)) {
                        return;
                    }

                    // For crawl: get body and set result
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

    /** {@inheritDoc} */
    #[Override]
    public function execute(array $requests): array
    {
        $results = [];
        $wg = new WaitGroup();

        foreach ($requests as $uri => $request) {
            $wg->add();
            Coroutine::create(static function () use ($uri, $request, &$results, $wg): void {
                try {
                    // Coroutines share memory, so we can write directly to $results
                    $results[$uri] = (string) $request();
                } finally {
                    $wg->done();
                }
            });
        }

        $wg->wait();

        return $results;
    }

    #[Override]
    public function isAvailable(): bool
    {
        return (extension_loaded('swoole') || extension_loaded('openswoole')) && Coroutine::getCid() > 0;
    }
}
