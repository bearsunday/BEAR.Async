<?php

declare(strict_types=1);

namespace BEAR\Async\Module;

use BEAR\Async\Adapter\SyncAsync;
use BEAR\Async\AsyncEmbedInterceptor;
use BEAR\Async\AsyncInterface;
use BEAR\Resource\EmbedInterceptorInterface;
use BEAR\Resource\Module\ResourceModule;
use PHPUnit\Framework\TestCase;
use Ray\Di\AbstractModule;
use Ray\Di\Injector;

class AsyncEmbedModuleTest extends TestCase
{
    public function testDocumentedInstallOrderKeepsAsyncBindings(): void
    {
        $module = new class extends AbstractModule {
            protected function configure(): void
            {
                $this->install(new AsyncEmbedModule());
                $this->install(new ResourceModule('FakeVendor\Sandbox'));
            }
        };

        $injector = new Injector($module);

        $this->assertInstanceOf(AsyncEmbedInterceptor::class, $injector->getInstance(EmbedInterceptorInterface::class));
    }

    public function testAsyncInterfaceDefaultsToSyncAsync(): void
    {
        $injector = new Injector(new AsyncEmbedModule());

        $this->assertInstanceOf(SyncAsync::class, $injector->getInstance(AsyncInterface::class));
    }
}
