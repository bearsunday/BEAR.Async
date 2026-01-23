<?php

declare(strict_types=1);

namespace BEAR\Async\Module;

use BEAR\Async\Adapter;
use BEAR\Async\Adapter\SyncAsync;
use BEAR\Async\AsyncInterface;
use BEAR\Async\AsyncLinker;
use BEAR\Resource\LinkerInterface;
use BEAR\Resource\Module\ResourceModule;
use PHPUnit\Framework\TestCase;
use Ray\Di\AbstractModule;
use Ray\Di\Injector;

class AsyncCrawlModuleTest extends TestCase
{
    public function testDefaultAdapterIsSync(): void
    {
        $module = new class extends AbstractModule {
            protected function configure(): void
            {
                $this->install(new ResourceModule('FakeVendor\Sandbox'));
                $this->install(new AsyncCrawlModule());
            }
        };

        $injector = new Injector($module);
        $async = $injector->getInstance(AsyncInterface::class);

        $this->assertInstanceOf(SyncAsync::class, $async);
    }

    public function testModuleCanBeInstantiated(): void
    {
        $module = new AsyncCrawlModule();

        // Verify the module can be instantiated
        $this->assertInstanceOf(AsyncCrawlModule::class, $module);
    }

    public function testExplicitSyncAdapter(): void
    {
        $module = new class extends AbstractModule {
            protected function configure(): void
            {
                $this->install(new ResourceModule('FakeVendor\Sandbox'));
                $this->install(new AsyncCrawlModule(Adapter::Sync));
            }
        };

        $injector = new Injector($module);
        $async = $injector->getInstance(AsyncInterface::class);

        $this->assertInstanceOf(SyncAsync::class, $async);
    }

    public function testLinkerInterfaceBinding(): void
    {
        $resourceModule = new ResourceModule('FakeVendor\Sandbox');
        $resourceModule->override(new AsyncCrawlModule());

        $injector = new Injector($resourceModule);
        $linker = $injector->getInstance(LinkerInterface::class);

        $this->assertInstanceOf(AsyncLinker::class, $linker);
    }
}
