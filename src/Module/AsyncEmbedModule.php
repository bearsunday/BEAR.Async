<?php

declare(strict_types=1);

namespace BEAR\Async\Module;

use BEAR\Async\AsyncEmbedInterceptor;
use BEAR\Async\AsyncHalRenderer;
use BEAR\Async\EmbedDataLoader;
use BEAR\Async\EmbedRequests;
use BEAR\Resource\EmbedInterceptorInterface;
use BEAR\Resource\RenderInterface;
use Override;
use Ray\Di\AbstractModule;
use Ray\Di\Scope;

/**
 * AsyncEmbedModule provides async/parallel loading for #[Embed] resources
 *
 * This module replaces the standard EmbedInterceptor with AsyncEmbedInterceptor
 * and HalRenderer with AsyncHalRenderer to enable parallel loading of
 * embedded resources.
 *
 * NOTE: This module is automatically installed by AsyncSwooleModule.
 * You don't need to install it separately when using AsyncSwooleModule.
 *
 * IMPORTANT: This module requires AsyncInterface to be bound.
 *
 * Usage (standalone):
 *   class AppModule extends AbstractModule
 *   {
 *       protected function configure(): void
 *       {
 *           $this->bind(AsyncInterface::class)->to(SwooleAsync::class);
 *           $this->install(new AsyncEmbedModule());
 *       }
 *   }
 *
 * Usage (recommended - with AsyncSwooleModule):
 *   class AppModule extends AbstractModule
 *   {
 *       protected function configure(): void
 *       {
 *           $this->install(new PackageModule());
 *           $this->install(new AsyncSwooleModule());  // Includes AsyncEmbedModule
 *       }
 *   }
 */
final class AsyncEmbedModule extends AbstractModule
{
    #[Override]
    protected function configure(): void
    {
        // EmbedRequests should be singleton (request-scoped) to collect across interceptors
        $this->bind(EmbedRequests::class)->in(Scope::SINGLETON);
        $this->bind(EmbedDataLoader::class);

        // Replace EmbedInterceptor with AsyncEmbedInterceptor via interface binding
        $this->bind(EmbedInterceptorInterface::class)->to(AsyncEmbedInterceptor::class);

        // Replace HalRenderer with AsyncHalRenderer
        $this->bind(RenderInterface::class)->to(AsyncHalRenderer::class);
    }
}
