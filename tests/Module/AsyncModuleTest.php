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

class AsyncModuleTest extends TestCase
{
    public function testDefaultAdapterIsSync(): void
    {
        $module = new class extends AbstractModule {
            protected function configure(): void
            {
                $this->install(new ResourceModule('FakeVendor\Sandbox'));
                $this->install(new AsyncModule());
            }
        };

        $injector = new Injector($module);
        $async = $injector->getInstance(AsyncInterface::class);

        $this->assertInstanceOf(SyncAsync::class, $async);
    }

    public function testModuleCanBeInstantiated(): void
    {
        $module = new AsyncModule();

        // Verify the module can be instantiated
        $this->assertInstanceOf(AsyncModule::class, $module);
    }

    public function testExplicitSyncAdapter(): void
    {
        $module = new class extends AbstractModule {
            protected function configure(): void
            {
                $this->install(new ResourceModule('FakeVendor\Sandbox'));
                $this->install(new AsyncModule(Adapter::Sync));
            }
        };

        $injector = new Injector($module);
        $async = $injector->getInstance(AsyncInterface::class);

        $this->assertInstanceOf(SyncAsync::class, $async);
    }

    public function testLinkerInterfaceBinding(): void
    {
        $resourceModule = new ResourceModule('FakeVendor\Sandbox');
        $resourceModule->override(new AsyncModule());

        $injector = new Injector($resourceModule);
        $linker = $injector->getInstance(LinkerInterface::class);

        $this->assertInstanceOf(AsyncLinker::class, $linker);
    }
}
