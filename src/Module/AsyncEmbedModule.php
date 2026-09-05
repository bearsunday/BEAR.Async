<?php

declare(strict_types=1);

namespace BEAR\Async\Module;

use BEAR\Async\Adapter\SyncAsync;
use BEAR\Async\AsyncEmbedInterceptor;
use BEAR\Async\AsyncInterface;
use BEAR\Async\PendingRequests;
use BEAR\Resource\EmbedInterceptorInterface;
use Override;
use Ray\Di\AbstractModule;
use Ray\Di\Scope;

/**
 * AsyncEmbedModule provides async/parallel loading for #[Embed] resources
 *
 * This module replaces the standard EmbedInterceptor with AsyncEmbedInterceptor
 * to enable parallel loading of embedded resources. AsyncEmbedInterceptor wraps
 * AbstractRequest objects with AsyncRequest, which triggers batch parallel
 * execution when rendered via PendingRequests (そうめん流し方式).
 *
 * NOTE: This module is automatically installed by AsyncSwooleModule and ParallelModule.
 * You don't need to install it separately when using those modules.
 *
 * AsyncInterface defaults to SyncAsync (sequential); ParallelModule and
 * AsyncSwooleModule bind their own adapter first, which wins over the default.
 *
 * Usage (standalone, sequential; from a context module):
 *   namespace MyVendor\MyApp\Module;
 *
 *   final class AsyncModule extends AbstractModule
 *   {
 *       protected function configure(): void
 *       {
 *           $this->install(new AsyncEmbedModule());
 *       }
 *   }
 */
final class AsyncEmbedModule extends AbstractModule
{
    #[Override]
    protected function configure(): void
    {
        // Default adapter; runtime modules bind theirs first and win (first-binding-wins)
        $this->bind(AsyncInterface::class)->to(SyncAsync::class)->in(Scope::SINGLETON);

        // PendingRequests must be singleton to collect all requests in one batch
        $this->bind(PendingRequests::class)->in(Scope::SINGLETON);

        // Replace EmbedInterceptorInterface with AsyncEmbedInterceptor
        $this->bind(EmbedInterceptorInterface::class)->to(AsyncEmbedInterceptor::class);
    }
}
