<?php

declare(strict_types=1);

namespace BEAR\Async\Module;

use BEAR\Async\Adapter\SyncAsync;
use BEAR\Async\AsyncInterface;
use BEAR\Async\AsyncLinker;
use BEAR\Resource\LinkerInterface;
use BEAR\Resource\Module\ResourceModule;
use PHPUnit\Framework\TestCase;
use Ray\Di\AbstractModule;
use Ray\Di\Injector;

class AsyncSyncModuleTest extends TestCase
{
    public function testModuleCanBeInstantiated(): void
    {
        $module = new AsyncSyncModule();

        $this->assertInstanceOf(AsyncSyncModule::class, $module);
    }

    public function testAsyncInterfaceBinding(): void
    {
        $module = new class extends AbstractModule {
            protected function configure(): void
            {
                $this->install(new ResourceModule('FakeVendor\Sandbox'));
                $this->install(new AsyncSyncModule());
            }
        };

        $injector = new Injector($module);
        $async = $injector->getInstance(AsyncInterface::class);

        $this->assertInstanceOf(SyncAsync::class, $async);
    }

    public function testLinkerInterfaceBinding(): void
    {
        $resourceModule = new ResourceModule('FakeVendor\Sandbox');
        $resourceModule->override(new AsyncSyncModule());

        $injector = new Injector($resourceModule);
        $linker = $injector->getInstance(LinkerInterface::class);

        $this->assertInstanceOf(AsyncLinker::class, $linker);
    }
}
