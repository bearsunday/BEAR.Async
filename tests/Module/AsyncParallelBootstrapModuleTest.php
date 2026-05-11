<?php

declare(strict_types=1);

namespace BEAR\Async\Module;

use BEAR\Async\Qualifier\Context;
use BEAR\Async\Worker\WorkerResourceCache;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\TestCase;
use Ray\Di\Injector;

class AsyncParallelBootstrapModuleTest extends TestCase
{
    protected function tearDown(): void
    {
        WorkerResourceCache::reset();
    }

    #[RequiresPhpExtension('parallel')]
    public function testContextIsBound(): void
    {
        $module = new AsyncParallelBootstrapModule('prod-hal-app', 4);
        $injector = new Injector($module);

        $context = $injector->getInstance('', Context::class);

        $this->assertSame('prod-hal-app', $context);
    }

    #[RequiresPhpExtension('parallel')]
    public function testInstallsAsyncParallelModule(): void
    {
        $module = new AsyncParallelBootstrapModule('prod-hal-app', 2);
        $injector = new Injector($module);

        // PoolSize should be bound by the installed AsyncParallelModule
        $poolSize = $injector->getInstance('', \BEAR\Async\Qualifier\PoolSize::class);

        $this->assertSame(2, $poolSize);
    }

    public function testEmptyContextRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new AsyncParallelBootstrapModule('', 4);
    }

    public function testNonPositivePoolSizeRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new AsyncParallelBootstrapModule('prod-hal-app', 0);
    }
}
