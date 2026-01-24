<?php

declare(strict_types=1);

namespace BEAR\AsyncDemo\Module;

use BEAR\Async\Module\AsyncParallelModule;
use Ray\Di\AbstractModule;

use function dirname;

/**
 * Parallel context module
 *
 * Install this module by using the "parallel" context (e.g., "prod-parallel-app")
 */
final class ParallelModule extends AbstractModule
{
    protected function configure(): void
    {
        $this->install(new AsyncParallelModule(
            namespace: 'BEAR\AsyncDemo',
            context: 'prod-parallel-app',
            appDir: dirname(__DIR__, 2),
        ));
    }
}
