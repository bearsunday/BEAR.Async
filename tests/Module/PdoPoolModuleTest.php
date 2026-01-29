<?php

declare(strict_types=1);

namespace BEAR\Async\Module;

use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\TestCase;
use Ray\Di\Injector;
use Swoole\Database\PDOPool;

#[RequiresPhpExtension('swoole')]
class PdoPoolModuleTest extends TestCase
{
    public function testModuleCanBeInstantiated(): void
    {
        $module = new PdoPoolModule('mysql:host=localhost;dbname=test', 'user', 'pass');

        $this->assertInstanceOf(PdoPoolModule::class, $module);
    }

    public function testPdoPoolBinding(): void
    {
        $module = new PdoPoolModule('mysql:host=localhost;dbname=test', 'user', 'pass', 2);
        $injector = new Injector($module);

        $pool = $injector->getInstance(PDOPool::class);

        $this->assertInstanceOf(PDOPool::class, $pool);
    }
}
