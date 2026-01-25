<?php

declare(strict_types=1);

namespace BEAR\Async\Module;

use BEAR\Async\Adapter\SwooleAsync;
use BEAR\Async\AsyncInterface;
use BEAR\Async\AsyncLinker;
use BEAR\Resource\LinkerInterface;
use BEAR\Resource\Module\ResourceModule;
use PHPUnit\Framework\TestCase;
use Ray\Di\AbstractModule;
use Ray\Di\Injector;

/**
 * @requires extension swoole
 */
class AsyncSwooleModuleTest extends TestCase
{
    public function testModuleCanBeInstantiated(): void
    {
        $module = new AsyncSwooleModule();

        $this->assertInstanceOf(AsyncSwooleModule::class, $module);
    }

    public function testAsyncInterfaceBinding(): void
    {
        $module = new class extends AbstractModule {
            protected function configure(): void
            {
                $this->install(new ResourceModule('FakeVendor\Sandbox'));
                $this->install(new AsyncSwooleModule());
            }
        };

        $injector = new Injector($module);
        $async = $injector->getInstance(AsyncInterface::class);

        $this->assertInstanceOf(SwooleAsync::class, $async);
    }

    public function testLinkerInterfaceBinding(): void
    {
        $resourceModule = new ResourceModule('FakeVendor\Sandbox');
        $resourceModule->override(new AsyncSwooleModule());

        $injector = new Injector($resourceModule);
        $linker = $injector->getInstance(LinkerInterface::class);

        $this->assertInstanceOf(AsyncLinker::class, $linker);
    }
}
