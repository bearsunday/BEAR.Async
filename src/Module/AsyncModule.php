<?php

declare(strict_types=1);

namespace BEAR\Async\Module;

use BEAR\Async\Adapter;
use BEAR\Async\Adapter\AmpAsync;
use BEAR\Async\Adapter\SwooleAsync;
use BEAR\Async\Adapter\SyncAsync;
use BEAR\Async\AsyncInterface;
use BEAR\Async\AsyncLinker;
use BEAR\Resource\LinkerInterface;
use Ray\Di\AbstractModule;

/**
 * AsyncModule provides parallel execution for resource requests
 *
 * This module replaces the standard Linker with AsyncLinker to enable
 * parallel execution of resource requests using the specified adapter.
 *
 * Usage:
 *   class AppModule extends AbstractModule
 *   {
 *       protected function configure(): void
 *       {
 *           $this->install(new PackageModule());
 *           $this->install(new AsyncModule(Adapter::Swoole));
 *       }
 *   }
 *
 * Available adapters:
 *   - Adapter::Swoole   - Swoole coroutines (requires ext-swoole + coroutine context)
 *   - Adapter::Amp      - Amp async/await (requires amphp/amp)
 *   - Adapter::Sync     - Synchronous fallback (default, always available)
 */
final class AsyncModule extends AbstractModule
{
    public function __construct(
        private readonly Adapter $adapter = Adapter::Sync,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $class = match ($this->adapter) {
            Adapter::Swoole => SwooleAsync::class,
            Adapter::Amp => AmpAsync::class,
            Adapter::Sync => SyncAsync::class,
        };

        $this->bind(AsyncInterface::class)->to($class);
        $this->bind(LinkerInterface::class)->to(AsyncLinker::class);
    }
}
