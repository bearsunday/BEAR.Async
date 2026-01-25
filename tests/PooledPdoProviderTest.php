<?php

declare(strict_types=1);

namespace BEAR\Async;

use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\TestCase;
use Swoole\Coroutine;

use function Swoole\Coroutine\run;

#[RequiresPhpExtension('swoole')]
class PooledPdoProviderTest extends TestCase
{
    public function testGetReturnsPdo(): void
    {
        run(function (): void {
            $pool = new PdoPool('sqlite::memory:', '', '', 1);
            $provider = new PooledPdoProvider($pool);

            $pdo = $provider->get();

            $this->assertInstanceOf(\PDO::class, $pdo);
        });
    }

    public function testDeferReturnsConnectionToPool(): void
    {
        $pdoFromProvider = null;
        $pdoFromPool = null;

        run(function () use (&$pdoFromProvider, &$pdoFromPool): void {
            $pool = new PdoPool('sqlite::memory:', '', '', 1);
            $provider = new PooledPdoProvider($pool);

            Coroutine::create(function () use ($provider, &$pdoFromProvider): void {
                $pdoFromProvider = $provider->get();
            });

            // Wait for the coroutine to finish and defer to execute
            Coroutine::sleep(0.01);

            // After the coroutine completes, the PDO should be back in the pool
            $pdoFromPool = $pool->get();
        });

        $this->assertInstanceOf(\PDO::class, $pdoFromProvider);
        $this->assertInstanceOf(\PDO::class, $pdoFromPool);
        $this->assertSame($pdoFromProvider, $pdoFromPool);
    }
}
