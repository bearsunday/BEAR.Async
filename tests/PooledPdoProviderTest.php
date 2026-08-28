<?php

declare(strict_types=1);

namespace BEAR\Async;

use BEAR\Async\Exception\NotInCoroutineException;
use BEAR\Async\Fake\FakePdoPool;
use PDO;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\TestCase;
use Swoole\Coroutine;
use Swoole\Database\PDOProxy;

#[RequiresPhpExtension('swoole')]
final class PooledPdoProviderTest extends TestCase
{
    public function testGetOutsideCoroutineThrows(): void
    {
        $provider = new PooledPdoProvider(new FakePdoPool([]), 0.1);

        $this->expectException(NotInCoroutineException::class);

        $provider->get();
    }

    public function testGetReturnsPooledPdoOncePerCoroutineAndDefersReturn(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $proxy = new PDOProxy(static fn (): PDO => $pdo);
        $pool = new FakePdoPool([$proxy]);
        $provider = new PooledPdoProvider($pool, 0.1);

        $first = null;
        $second = null;
        Coroutine\run(static function () use ($provider, &$first, &$second): void {
            $first = $provider->get();
            $second = $provider->get();
        });

        $this->assertSame($pdo, $first);
        $this->assertSame($first, $second);
        $this->assertSame(1, $pool->getCount);
        // The proxy went back to the pool exactly once, on coroutine end.
        $this->assertSame([$proxy], $pool->putLog);
    }
}
