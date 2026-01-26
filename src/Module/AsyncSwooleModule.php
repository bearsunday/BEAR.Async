<?php

declare(strict_types=1);

namespace BEAR\Async\Module;

use BEAR\Async\Adapter\SwooleAsync;
use BEAR\Async\AsyncInterface;
use BEAR\Async\AsyncLinkCrawler;
use BEAR\Async\Exception\ExtensionNotLoadedException;
use BEAR\Async\PendingRequests;
use BEAR\Async\SwoolePendingRequestsProvider;
use BEAR\Resource\LinkCrawlerInterface;
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
 * - Parallel linkCrawl() execution via AsyncLinkCrawler
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
        $this->bind(LinkCrawlerInterface::class)->to(AsyncLinkCrawler::class);

        // Install AsyncEmbedModule for parallel #[Embed] support
        $this->install(new AsyncEmbedModule());

        // Override PendingRequests binding to use coroutine-local provider
        // This prevents concurrent coroutines from sharing the same instance
        $this->bind(PendingRequests::class)->toProvider(SwoolePendingRequestsProvider::class);
    }
}
