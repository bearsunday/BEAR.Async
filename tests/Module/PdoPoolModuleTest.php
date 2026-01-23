<?php

declare(strict_types=1);

namespace BEAR\Async\Module;

use BEAR\Async\PdoPool;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\TestCase;
use Ray\Di\Injector;

use function Swoole\Coroutine\run;

#[RequiresPhpExtension('swoole')]
class PdoPoolModuleTest extends TestCase
{
    public function testModuleCanBeInstantiated(): void
    {
        $module = new PdoPoolModule('sqlite::memory:', '', '');

        $this->assertInstanceOf(PdoPoolModule::class, $module);
    }

    public function testPdoPoolBinding(): void
    {
        run(function (): void {
            $module = new PdoPoolModule('sqlite::memory:', '', '', 2);
            $injector = new Injector($module);

            $pool = $injector->getInstance(PdoPool::class);

            $this->assertInstanceOf(PdoPool::class, $pool);
        });
    }

    public function testPdoBinding(): void
    {
        run(function (): void {
            $module = new PdoPoolModule('sqlite::memory:', '', '', 2);
            $injector = new Injector($module);

            $pdo = $injector->getInstance(\PDO::class);

            $this->assertInstanceOf(\PDO::class, $pdo);
        });
    }

    public function testCustomPoolSize(): void
    {
        run(function (): void {
            $module = new PdoPoolModule('sqlite::memory:', '', '', 4);
            $injector = new Injector($module);

            $pool = $injector->getInstance(PdoPool::class);

            // Get 4 connections (should succeed since pool size is 4)
            $connections = [];
            for ($i = 0; $i < 4; $i++) {
                $connections[] = $pool->get();
            }

            $this->assertCount(4, $connections);

            // Return all connections
            foreach ($connections as $pdo) {
                $pool->put($pdo);
            }
        });
    }
}
