<?php

declare(strict_types=1);

namespace BEAR\Async\Module;

use BEAR\Async\Adapter\ParallelAsync;
use BEAR\Async\AsyncInterface;
use BEAR\Async\AsyncLinker;
use BEAR\Async\Qualifier\AppDir;
use BEAR\Async\Qualifier\AppNamespace;
use BEAR\Async\Qualifier\Context;
use BEAR\Async\Qualifier\PoolSize;
use BEAR\Resource\LinkerInterface;
use Ray\Di\AbstractModule;

use function exec;
use function is_numeric;
use function php_uname;
use function str_starts_with;
use function trim;

/**
 * AsyncParallelModule provides parallel execution using ext-parallel
 *
 * This module uses PHP's parallel extension for true parallel execution
 * across multiple threads. Each thread maintains its own bootstrapped
 * application instance.
 *
 * Requirements:
 * - PHP built with ZTS (Zend Thread Safety)
 * - ext-parallel installed
 *
 * Usage:
 *   class AppModule extends AbstractModule
 *   {
 *       protected function configure(): void
 *       {
 *           $this->install(new PackageModule());
 *           $this->install(new AsyncParallelModule(
 *               namespace: 'MyVendor\MyApp',
 *               context: 'prod-app',
 *               appDir: dirname(__DIR__),
 *           ));
 *       }
 *   }
 */
final class AsyncParallelModule extends AbstractModule
{
    /** @var positive-int */
    private readonly int $poolSize;

    /**
     * @param string            $namespace Application namespace (e.g., 'MyVendor\MyApp')
     * @param string            $context   Application context (e.g., 'prod-app', 'stage-app')
     * @param string            $appDir    Application root directory
     * @param positive-int|null $poolSize  Number of parallel runtimes (default: CPU cores)
     */
    public function __construct(
        private readonly string $namespace,
        private readonly string $context,
        private readonly string $appDir,
        int|null $poolSize = null,
    ) {
        $this->poolSize = $poolSize ?? self::detectCpuCores();

        parent::__construct();
    }

    protected function configure(): void
    {
        $this->bind()->annotatedWith(AppNamespace::class)->toInstance($this->namespace);
        $this->bind()->annotatedWith(Context::class)->toInstance($this->context);
        $this->bind()->annotatedWith(AppDir::class)->toInstance($this->appDir);
        $this->bind()->annotatedWith(PoolSize::class)->toInstance($this->poolSize);
        $this->bind(AsyncInterface::class)->to(ParallelAsync::class);
        $this->bind(LinkerInterface::class)->to(AsyncLinker::class);
    }

    /** @return positive-int */
    private static function detectCpuCores(): int
    {
        $os = php_uname('s');
        $command = str_starts_with($os, 'Darwin') ? 'sysctl -n hw.ncpu' : 'nproc';
        $result = exec($command);

        if (! is_numeric($result)) {
            return 4;
        }

        $cores = (int) trim($result);

        return $cores > 0 ? $cores : 4;
    }
}
