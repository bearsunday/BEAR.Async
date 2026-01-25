<?php

declare(strict_types=1);

namespace BEAR\Async\Module;

use BEAR\Async\Exception\MissingEnvException;
use BEAR\Async\PdoPool;
use PDO;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\TestCase;
use Ray\Di\Injector;

use function putenv;
use function Swoole\Coroutine\run;

#[RequiresPhpExtension('swoole')]
class PdoPoolEnvModuleTest extends TestCase
{
    protected function setUp(): void
    {
        putenv('TEST_PDO_DSN=sqlite::memory:');
        putenv('TEST_PDO_USER=');
        putenv('TEST_PDO_PASS=');
        putenv('TEST_PDO_POOL_SIZE=4');
    }

    protected function tearDown(): void
    {
        putenv('TEST_PDO_DSN');
        putenv('TEST_PDO_USER');
        putenv('TEST_PDO_PASS');
        putenv('TEST_PDO_POOL_SIZE');
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

    public function testPdoPoolBinding(): void
    {
        run(function (): void {
            $module = new PdoPoolEnvModule(
                'TEST_PDO_DSN',
                'TEST_PDO_USER',
                'TEST_PDO_PASS',
            );
            $injector = new Injector($module);

            $pool = $injector->getInstance(PdoPool::class);

            $this->assertInstanceOf(PdoPool::class, $pool);
        });
    }

    public function testPdoBinding(): void
    {
        run(function (): void {
            $module = new PdoPoolEnvModule(
                'TEST_PDO_DSN',
                'TEST_PDO_USER',
                'TEST_PDO_PASS',
            );
            $injector = new Injector($module);

            $pdo = $injector->getInstance(PDO::class);

            $this->assertInstanceOf(PDO::class, $pdo);
        });
    }

    public function testCustomPoolSizeFromEnv(): void
    {
        run(function (): void {
            $module = new PdoPoolEnvModule(
                'TEST_PDO_DSN',
                'TEST_PDO_USER',
                'TEST_PDO_PASS',
                'TEST_PDO_POOL_SIZE',
            );
            $injector = new Injector($module);

            $pool = $injector->getInstance(PdoPool::class);

            $connections = [];
            try {
                for ($i = 0; $i < 4; $i++) {
                    $connections[] = $pool->get();
                }

                $this->assertCount(4, $connections);
            } finally {
                foreach ($connections as $pdo) {
                    $pool->put($pdo);
                }
            }
        });
    }

    public function testDefaultPoolSize(): void
    {
        run(function (): void {
            $module = new PdoPoolEnvModule(
                'TEST_PDO_DSN',
                'TEST_PDO_USER',
                'TEST_PDO_PASS',
                '',
                2,
            );
            $injector = new Injector($module);

            $pool = $injector->getInstance(PdoPool::class);

            $this->assertInstanceOf(PdoPool::class, $pool);
        });
    }
}
