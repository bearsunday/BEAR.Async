<?php

declare(strict_types=1);

namespace BEAR\Async\Module;

use BEAR\Async\Mysqli\MysqliBatchExecutor;
use BEAR\Async\Mysqli\MysqliConnectionFactory;
use BEAR\Async\Mysqli\MysqliParamBinder;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\TestCase;
use Ray\Di\Injector;

#[RequiresPhpExtension('mysqli')]
class MysqliBatchModuleTest extends TestCase
{
    public function testModuleCanBeInstantiated(): void
    {
        $module = new MysqliBatchModule('localhost', 'user', 'pass', 'database');

        $this->assertInstanceOf(MysqliBatchModule::class, $module);
    }

    public function testConnectionFactoryBinding(): void
    {
        $module = new MysqliBatchModule('localhost', 'user', 'pass', 'database');
        $injector = new Injector($module);

        $factory = $injector->getInstance(MysqliConnectionFactory::class);

        $this->assertInstanceOf(MysqliConnectionFactory::class, $factory);
    }

    public function testParamBinderBinding(): void
    {
        $module = new MysqliBatchModule('localhost', 'user', 'pass', 'database');
        $injector = new Injector($module);

        $binder = $injector->getInstance(MysqliParamBinder::class);

        $this->assertInstanceOf(MysqliParamBinder::class, $binder);
    }

    public function testBatchExecutorBinding(): void
    {
        $module = new MysqliBatchModule('localhost', 'user', 'pass', 'database');
        $injector = new Injector($module);

        $executor = $injector->getInstance(MysqliBatchExecutor::class);

        $this->assertInstanceOf(MysqliBatchExecutor::class, $executor);
    }

    public function testModuleWithCustomPort(): void
    {
        $module = new MysqliBatchModule('localhost', 'user', 'pass', 'database', 3307);

        $this->assertInstanceOf(MysqliBatchModule::class, $module);
    }

    public function testModuleWithAllOptions(): void
    {
        $module = new MysqliBatchModule(
            'localhost',
            'user',
            'pass',
            'database',
            3306,
            '/var/run/mysqld/mysqld.sock',
            'utf8mb4',
        );

        $this->assertInstanceOf(MysqliBatchModule::class, $module);
    }
}
