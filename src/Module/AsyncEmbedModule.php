<?php

declare(strict_types=1);

namespace BEAR\Async\Module;

use BEAR\Async\AsyncRenderDecorator;
use BEAR\Resource\HalRenderer;
use BEAR\Resource\RenderInterface;
use Override;
use Ray\Di\AbstractModule;

/**
 * AsyncEmbedModule provides async/parallel loading for #[Embed] resources
 *
 * This module decorates the renderer with AsyncRenderDecorator to enable
 * parallel loading of embedded resources. The decorator pre-executes all
 * embedded requests in parallel before rendering, leveraging AbstractRequest's
 * built-in caching.
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
 *
 * To use a different renderer (default is HalRenderer):
 *   class AppModule extends AbstractModule
 *   {
 *       protected function configure(): void
 *       {
 *           $this->bind(RenderInterface::class)->annotatedWith('async.inner')->to(JsonRenderer::class);
 *           $this->install(new AsyncSwooleModule());
 *       }
 *   }
 */
final class AsyncEmbedModule extends AbstractModule
{
    #[Override]
    protected function configure(): void
    {
        // Bind inner renderer (default to HalRenderer)
        // Users can override this binding before installing this module
        $this->bind(RenderInterface::class)
            ->annotatedWith('async.inner')
            ->to(HalRenderer::class);

        // Bind main RenderInterface to async decorator
        $this->bind(RenderInterface::class)->to(AsyncRenderDecorator::class);
    }
}
