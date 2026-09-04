<?php

declare(strict_types=1);

namespace BEAR\Async\Module;

use BEAR\Async\Adapter\SwooleAsync;
use BEAR\Async\AsyncInterface;
use BEAR\Async\AsyncLinkCrawler;
use BEAR\Resource\LinkCrawlerInterface;
use BEAR\Resource\Module\ResourceModule;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\TestCase;
use Ray\Di\AbstractModule;
use Ray\Di\Injector;

#[RequiresPhpExtension('swoole')]
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
                $this->install(new AsyncSwooleModule());
                $this->install(new ResourceModule('FakeVendor\Sandbox'));
            }
        };

        $injector = new Injector($module);
        $async = $injector->getInstance(AsyncInterface::class);

        $this->assertInstanceOf(SwooleAsync::class, $async);
    }

    public function testLinkCrawlerInterfaceBinding(): void
    {
        $module = new class extends AbstractModule {
            protected function configure(): void
            {
                // The documented install order: async module first, framework module last
                $this->install(new AsyncSwooleModule());
                $this->install(new ResourceModule('FakeVendor\Sandbox'));
            }
        };

        $injector = new Injector($module);
        $linkCrawler = $injector->getInstance(LinkCrawlerInterface::class);

        $this->assertInstanceOf(AsyncLinkCrawler::class, $linkCrawler);
    }
}
