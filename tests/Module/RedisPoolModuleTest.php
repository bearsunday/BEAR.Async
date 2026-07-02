<?php

declare(strict_types=1);

namespace BEAR\Async\Module;

use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\TestCase;
use Ray\Di\Injector;
use Swoole\Database\RedisPool;

#[RequiresPhpExtension('swoole')]
#[RequiresPhpExtension('redis')]
class RedisPoolModuleTest extends TestCase
{
    public function testModuleCanBeInstantiated(): void
    {
        $module = new RedisPoolModule('127.0.0.1', 6379);

        $this->assertInstanceOf(RedisPoolModule::class, $module);
    }

    public function testRedisPoolBinding(): void
    {
        $module = new RedisPoolModule('127.0.0.1', 6379, poolSize: 2);
        $injector = new Injector($module);

        $pool = $injector->getInstance(RedisPool::class);

        $this->assertInstanceOf(RedisPool::class, $pool);
    }

    public function testDefaultBorrowTimeoutBinding(): void
    {
        $module = new RedisPoolModule('127.0.0.1', 6379, poolSize: 2);
        $injector = new Injector($module);

        $borrowTimeout = $injector->getInstance('', 'redis_pool_borrow_timeout');

        $this->assertSame(5.0, $borrowTimeout);
    }

    public function testCustomBorrowTimeoutBinding(): void
    {
        $module = new RedisPoolModule('127.0.0.1', 6379, poolSize: 2, borrowTimeout: 1.5);
        $injector = new Injector($module);

        $borrowTimeout = $injector->getInstance('', 'redis_pool_borrow_timeout');

        $this->assertSame(1.5, $borrowTimeout);
    }
}
