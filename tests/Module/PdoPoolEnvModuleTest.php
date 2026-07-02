<?php

declare(strict_types=1);

namespace BEAR\Async\Module;

use BEAR\Async\Exception\MissingEnvException;
use BEAR\Async\Exception\RuntimeException;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\TestCase;
use Ray\Di\Injector;
use Swoole\Database\PDOPool;

use function putenv;

#[RequiresPhpExtension('swoole')]
class PdoPoolEnvModuleTest extends TestCase
{
    protected function setUp(): void
    {
        putenv('TEST_PDO_DSN=mysql:host=localhost;dbname=test');
        putenv('TEST_PDO_USER=user');
        putenv('TEST_PDO_PASS=pass');
        putenv('TEST_PDO_POOL_SIZE=4');
        putenv('TEST_PDO_BORROW_TIMEOUT=2.5');
    }

    protected function tearDown(): void
    {
        putenv('TEST_PDO_DSN');
        putenv('TEST_PDO_USER');
        putenv('TEST_PDO_PASS');
        putenv('TEST_PDO_POOL_SIZE');
        putenv('TEST_PDO_BORROW_TIMEOUT');
    }

    public function testModuleCanBeInstantiated(): void
    {
        $module = new PdoPoolEnvModule(
            'TEST_PDO_DSN',
            'TEST_PDO_USER',
            'TEST_PDO_PASS',
        );

        $this->assertInstanceOf(PdoPoolEnvModule::class, $module);
    }

    public function testMissingEnvThrowsException(): void
    {
        putenv('TEST_PDO_DSN');

        $this->expectException(MissingEnvException::class);
        $this->expectExceptionMessage('Required environment variable "TEST_PDO_DSN" is not set');

        $module = new PdoPoolEnvModule(
            'TEST_PDO_DSN',
            'TEST_PDO_USER',
            'TEST_PDO_PASS',
        );
        new Injector($module);
    }

    public function testMissingEnvExceptionExtendsPackageRuntimeException(): void
    {
        putenv('TEST_PDO_DSN');

        try {
            $module = new PdoPoolEnvModule(
                'TEST_PDO_DSN',
                'TEST_PDO_USER',
                'TEST_PDO_PASS',
            );
            new Injector($module);
            $this->fail('MissingEnvException was not thrown');
        } catch (MissingEnvException $e) {
            $this->assertInstanceOf(RuntimeException::class, $e);
        }
    }

    public function testPdoPoolBinding(): void
    {
        $module = new PdoPoolEnvModule(
            'TEST_PDO_DSN',
            'TEST_PDO_USER',
            'TEST_PDO_PASS',
        );
        $injector = new Injector($module);

        $pool = $injector->getInstance(PDOPool::class);

        $this->assertInstanceOf(PDOPool::class, $pool);
    }

    public function testCustomPoolSizeFromEnv(): void
    {
        $module = new PdoPoolEnvModule(
            'TEST_PDO_DSN',
            'TEST_PDO_USER',
            'TEST_PDO_PASS',
            'TEST_PDO_POOL_SIZE',
        );
        $injector = new Injector($module);

        $pool = $injector->getInstance(PDOPool::class);

        $this->assertInstanceOf(PDOPool::class, $pool);
    }

    public function testDefaultPoolSize(): void
    {
        $module = new PdoPoolEnvModule(
            'TEST_PDO_DSN',
            'TEST_PDO_USER',
            'TEST_PDO_PASS',
            '',
            2,
        );
        $injector = new Injector($module);

        $pool = $injector->getInstance(PDOPool::class);

        $this->assertInstanceOf(PDOPool::class, $pool);
    }

    public function testCustomBorrowTimeoutFromEnv(): void
    {
        $module = new PdoPoolEnvModule(
            'TEST_PDO_DSN',
            'TEST_PDO_USER',
            'TEST_PDO_PASS',
            '',
            64,
            'TEST_PDO_BORROW_TIMEOUT',
        );
        $injector = new Injector($module);

        $borrowTimeout = $injector->getInstance('', 'pdo_pool_borrow_timeout');

        $this->assertSame(2.5, $borrowTimeout);
    }

    public function testDefaultBorrowTimeoutWhenEnvUnset(): void
    {
        $module = new PdoPoolEnvModule(
            'TEST_PDO_DSN',
            'TEST_PDO_USER',
            'TEST_PDO_PASS',
        );
        $injector = new Injector($module);

        $borrowTimeout = $injector->getInstance('', 'pdo_pool_borrow_timeout');

        $this->assertSame(5.0, $borrowTimeout);
    }

    public function testInvalidBorrowTimeoutFromEnvFallsBackToDefault(): void
    {
        putenv('TEST_PDO_BORROW_TIMEOUT=not-a-number');

        $module = new PdoPoolEnvModule(
            'TEST_PDO_DSN',
            'TEST_PDO_USER',
            'TEST_PDO_PASS',
            '',
            64,
            'TEST_PDO_BORROW_TIMEOUT',
            3.0,
        );
        $injector = new Injector($module);

        $borrowTimeout = $injector->getInstance('', 'pdo_pool_borrow_timeout');

        $this->assertSame(3.0, $borrowTimeout);
    }

    public function testNegativeBorrowTimeoutFromEnvFallsBackToDefault(): void
    {
        putenv('TEST_PDO_BORROW_TIMEOUT=-1');

        $module = new PdoPoolEnvModule(
            'TEST_PDO_DSN',
            'TEST_PDO_USER',
            'TEST_PDO_PASS',
            '',
            64,
            'TEST_PDO_BORROW_TIMEOUT',
            3.0,
        );
        $injector = new Injector($module);

        $borrowTimeout = $injector->getInstance('', 'pdo_pool_borrow_timeout');

        $this->assertSame(3.0, $borrowTimeout);
    }
}
