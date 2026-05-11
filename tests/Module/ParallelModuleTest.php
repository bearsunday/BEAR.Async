<?php

declare(strict_types=1);

namespace BEAR\Async\Module;

use BEAR\Async\Exception\RecursiveWorkerSpawnException;
use BEAR\Async\Worker\WorkerResourceCache;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\TestCase;
use Ray\Di\Injector;

#[RequiresPhpExtension('parallel')]
class ParallelModuleTest extends TestCase
{
    protected function tearDown(): void
    {
        WorkerResourceCache::reset();
    }

    public function testModuleCanBeInstantiatedWithDefaults(): void
    {
        $module = new ParallelModule();

        $this->assertInstanceOf(ParallelModule::class, $module);
    }

    public function testModuleCanBeInstantiatedWithExplicitPoolSize(): void
    {
        $module = new ParallelModule(poolSize: 4);

        $this->assertInstanceOf(ParallelModule::class, $module);
    }

    public function testConfigureFailsFastInsideWorker(): void
    {
        WorkerResourceCache::markAsWorker();

        $this->expectException(RecursiveWorkerSpawnException::class);
        new Injector(new ParallelModule(poolSize: 2));
    }
}
