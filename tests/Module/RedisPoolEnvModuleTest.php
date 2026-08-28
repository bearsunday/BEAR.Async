<?php

declare(strict_types=1);

namespace BEAR\Async\Module;

use BEAR\Async\Exception\InvalidEnvException;
use BEAR\Async\Exception\MissingEnvException;
use BEAR\Async\Exception\RuntimeException;
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
        putenv('TEST_REDIS_BORROW_TIMEOUT=2.5');
    }

    protected function tearDown(): void
    {
        putenv('TEST_REDIS_HOST');
        putenv('TEST_REDIS_PORT');
        putenv('TEST_REDIS_PASSWORD');
        putenv('TEST_REDIS_DB_INDEX');
        putenv('TEST_REDIS_POOL_SIZE');
        putenv('TEST_REDIS_BORROW_TIMEOUT');
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

    public function testMissingEnvExceptionExtendsPackageRuntimeException(): void
    {
        putenv('TEST_REDIS_HOST');

        try {
            $module = new RedisPoolEnvModule('TEST_REDIS_HOST');
            new Injector($module);
            $this->fail('MissingEnvException was not thrown');
        } catch (MissingEnvException $e) {
            $this->assertInstanceOf(RuntimeException::class, $e);
        }
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

    public function testCustomBorrowTimeoutFromEnv(): void
    {
        $module = new RedisPoolEnvModule(
            'TEST_REDIS_HOST',
            '',
            '',
            '',
            '',
            6379,
            0,
            64,
            'TEST_REDIS_BORROW_TIMEOUT',
        );
        $injector = new Injector($module);

        $borrowTimeout = $injector->getInstance('', 'redis_pool_borrow_timeout');

        $this->assertSame(2.5, $borrowTimeout);
    }

    public function testDefaultBorrowTimeoutWhenEnvUnset(): void
    {
        $module = new RedisPoolEnvModule('TEST_REDIS_HOST');
        $injector = new Injector($module);

        $borrowTimeout = $injector->getInstance('', 'redis_pool_borrow_timeout');

        $this->assertSame(5.0, $borrowTimeout);
    }

    public function testEmptyBorrowTimeoutEnvValueFallsBackToDefault(): void
    {
        putenv('TEST_REDIS_BORROW_TIMEOUT=');

        $module = new RedisPoolEnvModule(
            'TEST_REDIS_HOST',
            '',
            '',
            '',
            '',
            6379,
            0,
            64,
            'TEST_REDIS_BORROW_TIMEOUT',
            3.0,
        );
        $injector = new Injector($module);

        $borrowTimeout = $injector->getInstance('', 'redis_pool_borrow_timeout');

        $this->assertSame(3.0, $borrowTimeout);
    }

    public function testInvalidBorrowTimeoutFromEnvThrows(): void
    {
        putenv('TEST_REDIS_BORROW_TIMEOUT=not-a-number');

        $this->expectException(InvalidEnvException::class);

        new Injector(new RedisPoolEnvModule(
            'TEST_REDIS_HOST',
            '',
            '',
            '',
            '',
            6379,
            0,
            64,
            'TEST_REDIS_BORROW_TIMEOUT',
            3.0,
        ));
    }

    public function testNegativeBorrowTimeoutFromEnvThrows(): void
    {
        putenv('TEST_REDIS_BORROW_TIMEOUT=-1');

        $this->expectException(InvalidEnvException::class);

        new Injector(new RedisPoolEnvModule(
            'TEST_REDIS_HOST',
            '',
            '',
            '',
            '',
            6379,
            0,
            64,
            'TEST_REDIS_BORROW_TIMEOUT',
            3.0,
        ));
    }

    public function testInvalidPortFromEnvThrows(): void
    {
        putenv('TEST_REDIS_PORT=not-a-port');

        $this->expectException(InvalidEnvException::class);

        new Injector(new RedisPoolEnvModule('TEST_REDIS_HOST', 'TEST_REDIS_PORT'));
    }

    public function testNegativeDbIndexFromEnvThrows(): void
    {
        putenv('TEST_REDIS_DB_INDEX=-1');

        $this->expectException(InvalidEnvException::class);

        new Injector(new RedisPoolEnvModule(
            'TEST_REDIS_HOST',
            '',
            '',
            'TEST_REDIS_DB_INDEX',
        ));
    }
}
