<?php

declare(strict_types=1);

namespace BEAR\Async\Adapter;

use BEAR\Async\AsyncInterface;

use function array_keys;
use function extension_loaded;

/**
 * ext-parallel based async execution using true parallelism
 *
 * This implementation uses PHP's parallel extension for true parallel
 * execution across multiple threads. Each task runs in its own runtime.
 *
 * Note: This requires the parallel extension and ZTS PHP build.
 * Note: The closure passed to Runtime::run must be self-contained
 * and cannot capture objects that aren't serializable.
 *
 * @psalm-suppress UndefinedClass - parallel extension may not be installed
 */
final class ParallelAsync implements AsyncInterface
{
    public function __invoke(array $tasks): void
    {
        /** @psalm-suppress UndefinedClass */
        $runtimeClass = '\parallel\Runtime';

        $futures = [];
        $taskKeys = array_keys($tasks);

        foreach ($tasks as $task) {
            /** @psalm-suppress UndefinedClass */
            $runtime = new $runtimeClass();
            $request = $task->getRequest();
            /** @psalm-suppress UndefinedClass */
            $futures[] = $runtime->run(static function () use ($request): mixed {
                return ($request)()->body;
            });
        }

        foreach ($futures as $i => $future) {
            if ($future === null) {
                continue;
            }

            /** @var array<string, mixed>|null $result */
            $result = $future->value();
            $tasks[$taskKeys[$i]]->setResult($result);
        }
    }

    public function isAvailable(): bool
    {
        return extension_loaded('parallel');
    }
}
