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
}
