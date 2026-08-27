<?php

declare(strict_types=1);

namespace BEAR\Async\Adapter;

use Throwable;

/**
 * Collects per-task Throwables and rethrows the first one in task order
 *
 * The adapters share the same failure semantics: every task in a batch runs
 * to completion, then a single error is rethrown. Errors are recorded under
 * the task's key as they happen — under coroutines that is completion order,
 * which is nondeterministic — so throwFirst() walks the submission-ordered
 * keys to make the rethrown error deterministic regardless of timing.
 *
 * @internal
 */
final class TaskErrors
{
    /** @var array<array-key, Throwable> */
    private array $errors = [];

    public function add(int|string $key, Throwable $error): void
    {
        $this->errors[$key] = $error;
    }

    public function has(int|string $key): bool
    {
        return isset($this->errors[$key]);
    }

    /**
     * Rethrow the error belonging to the earliest key, if any
     *
     * @param list<int|string> $orderedKeys Task keys in submission order
     *
     * @throws Throwable
     */
    public function throwFirst(array $orderedKeys): void
    {
        foreach ($orderedKeys as $key) {
            if (isset($this->errors[$key])) {
                throw $this->errors[$key];
            }
        }
    }
}
