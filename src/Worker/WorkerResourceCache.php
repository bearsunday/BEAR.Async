<?php

declare(strict_types=1);

namespace BEAR\Async\Worker;

use BEAR\Async\Exception\InconsistentWorkerContextException;
use BEAR\Package\Injector as PackageInjector;
use BEAR\Resource\ResourceInterface;

use function assert;
use function sprintf;

/**
 * Per-Runtime resource cache for ext-parallel worker threads.
 *
 * Each ext-parallel `Runtime` has its own zend memory, so class-level static
 * state is naturally Runtime-local. We use this to:
 *   1. Mark the Runtime as a worker (so AsyncParallelModule fails fast if
 *      re-installed inside a worker — preventing recursive thread pool spawns)
 *   2. Lazily build a single ResourceInterface per Runtime and reuse it
 *      across tasks (avoids re-running `Injector::getInstance` per task)
 *   3. Guard against context drift — if a Runtime is asked to serve two
 *      different (name, context, appDir) tuples, fail loudly rather than
 *      silently mixing them.
 *
 * @internal
 */
final class WorkerResourceCache
{
    private static bool $isWorker = false;
    private static string|null $key = null;
    private static ResourceInterface|null $resource = null;

    /** @codeCoverageIgnore */
    private function __construct()
    {
    }

    public static function isWorker(): bool
    {
        return self::$isWorker;
    }

    public static function markAsWorker(): void
    {
        self::$isWorker = true;
    }

    /**
     * Return the per-Runtime ResourceInterface, building it on first call.
     *
     * @throws InconsistentWorkerContextException when called with a different key after initialization.
     */
    public static function getOrInit(string $name, string $context, string $appDir): ResourceInterface
    {
        $key = $name . '|' . $context . '|' . $appDir;
        if (self::$resource !== null) {
            if (self::$key !== $key) {
                throw new InconsistentWorkerContextException(sprintf(
                    'Worker Runtime already bound to %s but asked to serve %s. A single Runtime cannot mix contexts.',
                    (string) self::$key,
                    $key,
                ));
            }

            return self::$resource;
        }

        self::markAsWorker();
        self::$key = $key;
        assert($name !== '' && $context !== '' && $appDir !== '');
        self::$resource = PackageInjector::getInstance($name, $context, $appDir)
            ->getInstance(ResourceInterface::class);

        return self::$resource;
    }

    /**
     * Reset state. Intended for tests only.
     *
     * @internal
     */
    public static function reset(): void
    {
        self::$isWorker = false;
        self::$key = null;
        self::$resource = null;
    }
}
