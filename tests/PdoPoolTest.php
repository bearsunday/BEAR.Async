<?php

declare(strict_types=1);

namespace BEAR\Async;

use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\TestCase;

use function Swoole\Coroutine\run;

#[RequiresPhpExtension('swoole')]
class PdoPoolTest extends TestCase
{
    public function testPoolCreation(): void
    {
        run(function (): void {
            $pool = new PdoPool('sqlite::memory:', '', '', 2);

            $pdo1 = $pool->get();
            $this->assertInstanceOf(\PDO::class, $pdo1);

            $pdo2 = $pool->get();
            $this->assertInstanceOf(\PDO::class, $pdo2);

            $this->assertNotSame($pdo1, $pdo2);

            $pool->put($pdo1);
            $pool->put($pdo2);
        });
    }

    public function testGetReturnsValidPdo(): void
    {
        run(function (): void {
            $pool = new PdoPool('sqlite::memory:', '', '', 1);
            $pdo = $pool->get();

            $this->assertInstanceOf(\PDO::class, $pdo);
            $this->assertSame(\PDO::ERRMODE_EXCEPTION, $pdo->getAttribute(\PDO::ATTR_ERRMODE));

            $pool->put($pdo);
        });
    }

    public function testPutReturnsConnectionToPool(): void
    {
        run(function (): void {
            $pool = new PdoPool('sqlite::memory:', '', '', 1);

            $pdo1 = $pool->get();
            $pool->put($pdo1);

            $pdo2 = $pool->get();
            $this->assertSame($pdo1, $pdo2);

            $pool->put($pdo2);
        });
    }
}
