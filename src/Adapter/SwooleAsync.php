<?php

declare(strict_types=1);

namespace BEAR\Async\Adapter;

use BEAR\Async\AsyncInterface;
use BEAR\Async\AsyncRequest;
use BEAR\Async\RequestTask;
use Override;
use Swoole\Coroutine;
use Swoole\Coroutine\WaitGroup;
use Throwable;

use function array_key_first;

/**
 * Swoole-based async execution using coroutines and WaitGroup
 *
 * This implementation leverages Swoole's coroutine system for parallel
 * execution. All tasks are executed concurrently, and the WaitGroup
 * ensures we wait for all tasks to complete before returning.
 *
 * Failure semantics: a Throwable raised while executing one task/request
 * does not abort its siblings or crash the Swoole worker. Every task is
 * always allowed to run to completion (successful ones still call
 * setResult() / populate the results array); once all coroutines have
 * finished, the first Throwable encountered (in task/request iteration
 * order) is rethrown to the caller, preserving its original type.
 *
 * Note: This only works when running inside a Swoole coroutine context
 * (e.g., inside a Swoole server or Coroutine::create block).
 */
final class SwooleAsync implements AsyncInterface
{
    #[Override]
    public function __invoke(array $tasks): void
    {
        $wg = new WaitGroup();
        /** @var array<string, Throwable> $errors */
        $errors = [];

        foreach ($tasks as $key => $task) {
            $wg->add();
            Coroutine::create(function () use ($key, $task, $wg, &$errors): void {
                try {
                    if (! ($task instanceof RequestTask)) {
                        return;
                    }

                    // For crawl: get body and set result
                    $result = ($task->getRequest())()->body;
                    /** @var array<string, mixed>|null $result */
                    $task->setResult($result);
                } catch (Throwable $e) {
                    $errors[$key] = $e;
                } finally {
                    $wg->done();
                }
            });
        }

        $wg->wait();

        if ($errors !== []) {
            throw $errors[array_key_first($errors)];
        }
    }

    /** {@inheritDoc} */
    #[Override]
    public function execute(array $requests): array
    {
        $results = [];
        $wg = new WaitGroup();
        /** @var array<string, Throwable> $errors */
        $errors = [];

        foreach ($requests as $uri => $request) {
            $wg->add();
            Coroutine::create(static function () use ($uri, $request, &$results, &$errors, $wg): void {
                try {
                    // Coroutines share memory, so we can write directly to $results
                    $results[$uri] = (string) $request();
                } catch (Throwable $e) {
                    $errors[$uri] = $e;
                } finally {
                    $wg->done();
                }
            });
        }

        $wg->wait();

        if ($errors !== []) {
            throw $errors[array_key_first($errors)];
        }

        return $results;
    }
}
