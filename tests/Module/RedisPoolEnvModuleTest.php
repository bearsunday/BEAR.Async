<?php

declare(strict_types=1);

namespace BEAR\Async\Module;

use BEAR\Async\Exception\MissingEnvException;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\TestCase;
use Ray\Di\Injector;
use Swoole\Database\RedisPool;

use function putenv;

#[RequiresPhpExtension('swoole')]
#[RequiresPhpExtension('redis')]
class RedisPoolEnvModuleTest extends TestCase
{
    protected function setUp(): void
    {
        putenv('TEST_REDIS_HOST=127.0.0.1');
        putenv('TEST_REDIS_PORT=6379');
        putenv('TEST_REDIS_PASSWORD=secret');
        putenv('TEST_REDIS_DB_INDEX=1');
        putenv('TEST_REDIS_POOL_SIZE=4');
    }

    protected function tearDown(): void
    {
        putenv('TEST_REDIS_HOST');
        putenv('TEST_REDIS_PORT');
        putenv('TEST_REDIS_PASSWORD');
        putenv('TEST_REDIS_DB_INDEX');
        putenv('TEST_REDIS_POOL_SIZE');
    }

    public function testModuleCanBeInstantiated(): void
    {
        $module = new RedisPoolEnvModule('TEST_REDIS_HOST');

        $this->assertInstanceOf(RedisPoolEnvModule::class, $module);
    }

    public function testMissingEnvThrowsException(): void
    {
        putenv('TEST_REDIS_HOST');

        $this->expectException(MissingEnvException::class);
        $this->expectExceptionMessage('Required environment variable "TEST_REDIS_HOST" is not set');

        $module = new RedisPoolEnvModule('TEST_REDIS_HOST');
        new Injector($module);
    }

    public function testRedisPoolBinding(): void
    {
        $module = new RedisPoolEnvModule('TEST_REDIS_HOST');
        $injector = new Injector($module);

        $pool = $injector->getInstance(RedisPool::class);

        $this->assertInstanceOf(RedisPool::class, $pool);
    }

    public function testCustomPoolSizeFromEnv(): void
    {
        $module = new RedisPoolEnvModule(
            'TEST_REDIS_HOST',
            'TEST_REDIS_PORT',
            'TEST_REDIS_PASSWORD',
            'TEST_REDIS_DB_INDEX',
            'TEST_REDIS_POOL_SIZE',
        );
        $injector = new Injector($module);

        $pool = $injector->getInstance(RedisPool::class);

        $this->assertInstanceOf(RedisPool::class, $pool);
    }

    public function testDefaultPoolSize(): void
    {
        $module = new RedisPoolEnvModule(
            'TEST_REDIS_HOST',
            '',
            '',
            '',
            '',
            6379,
            0,
            2,
        );
        $injector = new Injector($module);

        $pool = $injector->getInstance(RedisPool::class);

        $this->assertInstanceOf(RedisPool::class, $pool);
    }
}
