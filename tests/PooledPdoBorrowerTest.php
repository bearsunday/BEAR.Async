<?php

declare(strict_types=1);

namespace BEAR\Async;

use BEAR\Async\Exception\NotInCoroutineException;
use BEAR\Async\Exception\PoolTimeoutException;
use BEAR\Async\Exception\StalePooledConnectionException;
use BEAR\Async\Fake\FakeDeadPdo;
use BEAR\Async\Fake\FakePdoPool;
use PDO;
use PDOException;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\TestCase;
use Swoole\Coroutine;
use Swoole\Database\PDOProxy;
use Throwable;

#[RequiresPhpExtension('swoole')]
final class PooledPdoBorrowerTest extends TestCase
{
    public function testBorrowOutsideCoroutineThrows(): void
    {
        $borrower = new PooledPdoBorrower(new FakePdoPool([]), 0.1);

        $this->expectException(NotInCoroutineException::class);

        $borrower->borrow();
    }

    public function testBorrowReturnsLivePdoAndDefersReturn(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $proxy = new PDOProxy(static fn (): PDO => $pdo);
        $pool = new FakePdoPool([$proxy]);
        $borrower = new PooledPdoBorrower($pool, 0.1);

        $borrowed = null;
        Coroutine\run(static function () use ($borrower, &$borrowed): void {
            $borrowed = $borrower->borrow();
        });

        $this->assertSame($pdo, $borrowed);
        // The proxy went back to the pool exactly once, on coroutine end.
        $this->assertSame([$proxy], $pool->putLog);
    }

    public function testRepeatedBorrowInSameCoroutineReusesCheckout(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pool = new FakePdoPool([new PDOProxy(static fn (): PDO => $pdo)]);
        $borrower = new PooledPdoBorrower($pool, 0.1);

        $first = null;
        $second = null;
        Coroutine\run(static function () use ($borrower, &$first, &$second): void {
            $first = $borrower->borrow();
            $second = $borrower->borrow();
        });

        $this->assertSame($pdo, $first);
        $this->assertSame($first, $second);
        $this->assertSame(1, $pool->getCount);
        $this->assertCount(1, $pool->putLog);
    }

    public function testDeadCheckoutIsDiscardedAndRetried(): void
    {
        $livePdo = new PDO('sqlite::memory:');
        $liveProxy = new PDOProxy(static fn (): PDO => $livePdo);
        $pool = new FakePdoPool([
            new PDOProxy(static fn (): PDO => new FakeDeadPdo()),
            $liveProxy,
        ]);
        $borrower = new PooledPdoBorrower($pool, 0.1);

        $borrowed = null;
        Coroutine\run(static function () use ($borrower, &$borrowed): void {
            $borrowed = $borrower->borrow();
        });

        $this->assertSame($livePdo, $borrowed);
        // Dead slot freed with put(null); live proxy returned on coroutine end.
        $this->assertSame([null, $liveProxy], $pool->putLog);
        $this->assertSame(2, $pool->getCount);
    }

    public function testSecondDeadCheckoutThrowsStaleWithDriverErrorAttached(): void
    {
        $pool = new FakePdoPool([
            new PDOProxy(static fn (): PDO => new FakeDeadPdo()),
            new PDOProxy(static fn (): PDO => new FakeDeadPdo()),
        ]);
        $borrower = new PooledPdoBorrower($pool, 0.1);

        $caught = null;
        Coroutine\run(static function () use ($borrower, &$caught): void {
            try {
                $borrower->borrow();
            } catch (Throwable $e) {
                $caught = $e;
            }
        });

        $this->assertInstanceOf(StalePooledConnectionException::class, $caught);
        $this->assertInstanceOf(PDOException::class, $caught->getPrevious());
        // Both dead slots were freed.
        $this->assertSame([null, null], $pool->putLog);
    }

    public function testExhaustedPoolThrowsPoolTimeout(): void
    {
        $borrower = new PooledPdoBorrower(new FakePdoPool([]), 0.1);

        $caught = null;
        Coroutine\run(static function () use ($borrower, &$caught): void {
            try {
                $borrower->borrow();
            } catch (Throwable $e) {
                $caught = $e;
            }
        });

        $this->assertInstanceOf(PoolTimeoutException::class, $caught);
    }

    public function testFailingReplacementDialOnDiscardIsSwallowed(): void
    {
        $livePdo = new PDO('sqlite::memory:');
        $pool = new FakePdoPool([
            new PDOProxy(static fn (): PDO => new FakeDeadPdo()),
            new PDOProxy(static fn (): PDO => $livePdo),
        ]);
        $pool->throwOnDiscard = true;
        $borrower = new PooledPdoBorrower($pool, 0.1);

        $borrowed = null;
        Coroutine\run(static function () use ($borrower, &$borrowed): void {
            $borrowed = $borrower->borrow();
        });

        // put(null)'s failed replacement dial must not abort the retry.
        $this->assertSame($livePdo, $borrowed);
    }
}
