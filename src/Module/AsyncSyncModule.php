<?php

declare(strict_types=1);

namespace BEAR\Async\Module;

use BEAR\Async\Adapter\SyncAsync;
use BEAR\Async\AsyncInterface;
use BEAR\Async\AsyncLinker;
use BEAR\Resource\LinkerInterface;
use Ray\Di\AbstractModule;

/**
 * AsyncSyncModule provides synchronous fallback execution
 *
 * This module executes tasks sequentially. It serves as a fallback
 * when no async runtime (Swoole, Parallel) is available, ensuring
 * the crawl functionality works in any environment.
 *
 * Usage:
 *   class AppModule extends AbstractModule
 *   {
 *       protected function configure(): void
 *       {
 *           $this->install(new PackageModule());
 *           $this->install(new AsyncSyncModule());
 *       }
 *   }
 */
final class AsyncSyncModule extends AbstractModule
{
    protected function configure(): void
    {
        $this->bind(AsyncInterface::class)->to(SyncAsync::class);
        $this->bind(LinkerInterface::class)->to(AsyncLinker::class);
    }
}
