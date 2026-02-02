<?php

declare(strict_types=1);

namespace BEAR\AsyncDemo\Module;

use BEAR\Async\Adapter\SyncAsync;
use BEAR\Async\AsyncInterface;
use BEAR\Async\Module\AsyncEmbedModule;
use BEAR\AsyncDemo\Renderer\SlowtyTemplateEngine;
use BEAR\Resource\RenderInterface;
use Ray\Di\AbstractModule;

/**
 * AsyncModule provides async/parallel loading for #[Embed] resources
 *
 * This context module should be used in a context string where it comes
 * BEFORE 'hal' so that it's installed AFTER HalModule (contexts are processed
 * in reverse order). For example: 'cli-async-hal-api-app'
 *
 * Processing order for 'cli-async-hal-api-app':
 *   AppModule → ApiModule → HalModule → AsyncModule → CliModule
 */
final class AsyncModule extends AbstractModule
{
    protected function configure(): void
    {
        // Bind AsyncInterface (SyncAsync for sequential fallback)
        $this->bind(AsyncInterface::class)->to(SyncAsync::class);

        // Use SlowtyTemplateEngine as the inner renderer
        $this->bind()->annotatedWith('slowty_delay_ms')->toInstance(5);
        $this->bind(RenderInterface::class)->annotatedWith('async.inner')->to(SlowtyTemplateEngine::class);

        // Install AsyncEmbedModule to decorate the renderer
        $this->install(new AsyncEmbedModule());
    }
}
