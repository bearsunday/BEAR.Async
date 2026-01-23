<?php

declare(strict_types=1);

namespace BEAR\Async\Adapter;

use BEAR\Async\AsyncInterface;

use function call_user_func;
use function class_exists;

/**
 * Amp-based async execution using async/await pattern
 *
 * This implementation uses Amp's async/await pattern for concurrent execution.
 * All tasks are launched as async operations and awaited together.
 *
 * @psalm-suppress UndefinedClass
 * @psalm-suppress UndefinedFunction
 */
final class AmpAsync implements AsyncInterface
{
    /** @psalm-suppress UndefinedClass */
    private const AMP_FUTURE_CLASS = 'Amp\Future';

    public function __invoke(array $tasks): void
    {
        $futures = [];
        foreach ($tasks as $task) {
            $futures[] = call_user_func('Amp\async', static function () use ($task): void {
                $result = ($task->getRequest())()->body;
                /** @var array<string, mixed>|null $result */
                $task->setResult($result);
            });
        }

        call_user_func('Amp\Future\await', $futures);
    }

    public function isAvailable(): bool
    {
        return class_exists(self::AMP_FUTURE_CLASS);
    }
}
