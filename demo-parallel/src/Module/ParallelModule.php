<?php

declare(strict_types=1);

namespace BEAR\AsyncDemo\Module;

use BEAR\Async\Module\AsyncParallelModule;
use Ray\Di\AbstractModule;

use function dirname;

/**
 * Parallel context module
 *
 * Install this module by using the "parallel" context (e.g., "prod-parallel-hal-app")
 *
 * IMPORTANT: The context passed to AsyncParallelModule is used by worker threads.
 * It must NOT include 'parallel' to avoid recursive thread pool creation.
 */
final class ParallelModule extends AbstractModule
{
    protected function configure(): void
    {
        // Worker threads use 'prod-hal-app' (without parallel) to avoid recursive thread pools
        $this->install(new AsyncParallelModule(
            namespace: 'BEAR\AsyncDemo',
            context: 'prod-hal-app',
            appDir: dirname(__DIR__, 2),
        ));
    }
}
