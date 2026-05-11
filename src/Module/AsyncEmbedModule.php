<?php

declare(strict_types=1);

namespace BEAR\Async\Module;

use BEAR\Async\PendingRequests;
use BEAR\Async\AsyncEmbedInterceptor;
use BEAR\Resource\EmbedInterceptor;
use BEAR\Resource\EmbedInterceptorInterface;
use Override;
use Ray\Aop\MethodInterceptor;
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
        // PendingRequests must be singleton to collect all requests in one batch
        $this->bind(PendingRequests::class)->in(Scope::SINGLETON);

        // Bind inner interceptor (the standard EmbedInterceptor)
        $this->bind(MethodInterceptor::class)
            ->annotatedWith('async.embed.inner')
            ->to(EmbedInterceptor::class);

        // Replace EmbedInterceptorInterface with AsyncEmbedInterceptor
        $this->bind(EmbedInterceptorInterface::class)->to(AsyncEmbedInterceptor::class);
    }
}
