<?php

declare(strict_types=1);

namespace BEAR\Async\Module;

use Aura\Sql\ExtendedPdoInterface;
use BEAR\Async\Exception\NotInCoroutineException;
use BEAR\Async\Fake\FakePdoPool;
use BEAR\Async\PooledPdoProvider;
use PDO;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\TestCase;
use Swoole\Coroutine;
use Swoole\Database\PDOProxy;

#[RequiresPhpExtension('swoole')]
final class PooledExtendedPdoProviderTest extends TestCase
{
    public function testGetOutsideCoroutineThrows(): void
    {
        $provider = new PooledExtendedPdoProvider(new FakePdoPool([]), 0.1);

        $this->expectException(NotInCoroutineException::class);

        $provider->get();
    }

    public function testGetWrapsPooledPdoOncePerCoroutineAndDefersReturn(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $proxy = new PDOProxy(static fn (): PDO => $pdo);
        $pool = new FakePdoPool([$proxy]);
        $provider = new PooledExtendedPdoProvider($pool, 0.1);

        $first = null;
        $second = null;
        Coroutine\run(static function () use ($provider, &$first, &$second): void {
            $first = $provider->get();
            $second = $provider->get();
        });

        $this->assertInstanceOf(ExtendedPdoInterface::class, $first);
        $this->assertSame($pdo, $first->getPdo());
        $this->assertSame($first, $second);
        $this->assertSame(1, $pool->getCount);
        // The proxy went back to the pool exactly once, on coroutine end.
        $this->assertSame([$proxy], $pool->putLog);
    }

    public function testSharesCheckoutWithPooledPdoProviderInSameCoroutine(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pool = new FakePdoPool([new PDOProxy(static fn (): PDO => $pdo)]);
        $pdoProvider = new PooledPdoProvider($pool, 0.1);
        $extendedProvider = new PooledExtendedPdoProvider($pool, 0.1);

        $plain = null;
        $extended = null;
        Coroutine\run(static function () use ($pdoProvider, $extendedProvider, &$plain, &$extended): void {
            $plain = $pdoProvider->get();
            $extended = $extendedProvider->get();
        });

        $this->assertInstanceOf(ExtendedPdoInterface::class, $extended);
        $this->assertSame($plain, $extended->getPdo());
        $this->assertSame(1, $pool->getCount);
        $this->assertCount(1, $pool->putLog);
    }

    public function testCheckoutIsCoroutineLocal(): void
    {
        $pool = new FakePdoPool([
            new PDOProxy(static fn (): PDO => new PDO('sqlite::memory:')),
            new PDOProxy(static fn (): PDO => new PDO('sqlite::memory:')),
        ]);
        $provider = new PooledExtendedPdoProvider($pool, 0.1);

        $seen = [];
        Coroutine\run(static function () use ($provider, &$seen): void {
            Coroutine::create(static function () use ($provider, &$seen): void {
                $seen[] = $provider->get();
            });
            Coroutine::create(static function () use ($provider, &$seen): void {
                $seen[] = $provider->get();
            });
        });

        $this->assertCount(2, $seen);
        $this->assertNotSame($seen[0], $seen[1]);
        $this->assertNotSame($seen[0]->getPdo(), $seen[1]->getPdo());
        $this->assertSame(2, $pool->getCount);
        $this->assertCount(2, $pool->putLog);
    }
}
