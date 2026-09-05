<?php

declare(strict_types=1);

namespace BEAR\Async\Module;

use BEAR\Async\Adapter\SwooleAsync;
use BEAR\Async\AsyncInterface;
use BEAR\Async\AsyncLinkCrawler;
use BEAR\Async\PendingRequests;
use BEAR\Resource\LinkCrawlerInterface;
use BEAR\Resource\Module\ResourceModule;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\TestCase;
use Ray\Di\AbstractModule;
use Ray\Di\Injector;
use Swoole\Coroutine;
use Swoole\Coroutine\WaitGroup;

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
                $this->install(new AsyncSwooleModule());
                $this->install(new ResourceModule('FakeVendor\Sandbox'));
            }
        };

        $injector = new Injector($module);
        $linkCrawler = $injector->getInstance(LinkCrawlerInterface::class);

        $this->assertInstanceOf(AsyncLinkCrawler::class, $linkCrawler);
    }

    public function testPendingRequestsIsCoroutineLocalThroughInjector(): void
    {
        $module = new class extends AbstractModule {
            protected function configure(): void
            {
                $this->install(new AsyncSwooleModule());
                $this->install(new ResourceModule('FakeVendor\Sandbox'));
            }
        };
        $injector = new Injector($module);

        /** @var array<int, array{PendingRequests, PendingRequests}> $held */
        $held = [];
        Coroutine\run(static function () use ($injector, &$held): void {
            $wg = new WaitGroup();
            foreach ([1, 2] as $n) {
                $wg->add();
                Coroutine::create(static function () use ($injector, &$held, $n, $wg): void {
                    $held[$n] = [$injector->getInstance(PendingRequests::class), $injector->getInstance(PendingRequests::class)];
                    // Keep both coroutines alive together so object ids cannot be recycled
                    Coroutine::sleep(0.01);
                    $wg->done();
                });
            }

            $wg->wait();
        });

        $this->assertSame($held[1][0], $held[1][1]);
        $this->assertNotSame($held[1][0], $held[2][0]);
    }
}
