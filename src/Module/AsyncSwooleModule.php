<?php

declare(strict_types=1);

namespace BEAR\Async\Module;

use BEAR\Async\Adapter\SwooleAsync;
use BEAR\Async\AsyncInterface;
use BEAR\Async\AsyncLinker;
use BEAR\Async\Exception\ExtensionNotLoadedException;
use BEAR\Resource\LinkerInterface;
use Override;
use Ray\Di\AbstractModule;
use Ray\Di\Scope;

use function extension_loaded;

/**
 * AsyncSwooleModule provides parallel execution using Swoole coroutines
 *
 * This module uses Swoole's coroutine system for parallel execution.
 * All tasks are executed concurrently using WaitGroup.
 *
 * Features:
 * - Parallel linkCrawl() execution via AsyncLinker
 * - Parallel #[Embed] execution via AsyncEmbedModule
 *
 * Requirements:
 * - ext-swoole installed
 * - Running inside a Swoole coroutine context
 *
 * Usage:
 *   class AppModule extends AbstractModule
 *   {
 *       protected function configure(): void
 *       {
 *           $this->install(new PackageModule());
 *           $this->install(new AsyncSwooleModule());
 *       }
 *   }
 */
final class AsyncSwooleModule extends AbstractModule
{
    #[Override]
    protected function configure(): void
    {
        if (! extension_loaded('swoole') && ! extension_loaded('openswoole')) {
            throw new ExtensionNotLoadedException('ext-swoole or ext-openswoole is required. Install with: pecl install swoole');
        }

        $this->bind(AsyncInterface::class)->to(SwooleAsync::class)->in(Scope::SINGLETON);
        $this->bind(LinkerInterface::class)->to(AsyncLinker::class);

        // Install AsyncEmbedModule for parallel #[Embed] support
        $this->install(new AsyncEmbedModule());
    }
}
