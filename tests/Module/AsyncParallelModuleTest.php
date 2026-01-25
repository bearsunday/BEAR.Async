<?php

declare(strict_types=1);

namespace BEAR\Async\Module;

use BEAR\Async\Adapter\ParallelAsync;
use BEAR\Async\AsyncInterface;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\TestCase;
use Ray\Di\Injector;

#[RequiresPhpExtension('parallel')]
class AsyncParallelModuleTest extends TestCase
{
    public function testModuleCanBeInstantiated(): void
    {
        $module = new AsyncParallelModule(
            namespace: 'MyVendor\MyApp',
            context: 'prod-app',
            appDir: '/path/to/app',
        );

        $this->assertInstanceOf(AsyncParallelModule::class, $module);
    }

    public function testAsyncInterfaceBinding(): void
    {
        $module = new AsyncParallelModule(
            namespace: 'MyVendor\MyApp',
            context: 'prod-app',
            appDir: '/path/to/app',
            poolSize: 4,
        );
        $injector = new Injector($module);

        $async = $injector->getInstance(AsyncInterface::class);

        $this->assertInstanceOf(ParallelAsync::class, $async);
    }
}
