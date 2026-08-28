<?php

declare(strict_types=1);

namespace BEAR\Async;

use BEAR\Async\Exception\NotInCoroutineException;
use BEAR\Async\Exception\PoolTimeoutException;
use BEAR\Async\Exception\StalePooledConnectionException;
use BEAR\Async\Fake\FakeDeadRedis;
use BEAR\Async\Fake\FakeLiveRedis;
use BEAR\Async\Fake\FakeRedisPool;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\TestCase;
use RedisException;
use Swoole\Coroutine;
use Throwable;

#[RequiresPhpExtension('swoole')]
#[RequiresPhpExtension('redis')]
final class PooledRedisProviderTest extends TestCase
{
    public function testGetOutsideCoroutineThrows(): void
    {
        $provider = new PooledRedisProvider(new FakeRedisPool([]), 0.1);

        $this->expectException(NotInCoroutineException::class);

        $provider->get();
    }

    public function testGetReturnsLiveRedisAndDefersReturn(): void
    {
        $redis = new FakeLiveRedis();
        $pool = new FakeRedisPool([$redis]);
        $provider = new PooledRedisProvider($pool, 0.1);

        $checkedOut = null;
        Coroutine\run(static function () use ($provider, &$checkedOut): void {
            $checkedOut = $provider->get();
        });

        $this->assertSame($redis, $checkedOut);
        $this->assertSame(1, $redis->pingCount);
        // The connection went back to the pool exactly once, on coroutine end.
        $this->assertSame([$redis], $pool->putLog);
    }

    public function testRepeatedGetInSameCoroutineReusesCheckout(): void
    {
        $redis = new FakeLiveRedis();
        $pool = new FakeRedisPool([$redis]);
        $provider = new PooledRedisProvider($pool, 0.1);

        $first = null;
        $second = null;
        Coroutine\run(static function () use ($provider, &$first, &$second): void {
            $first = $provider->get();
            $second = $provider->get();
        });

        $this->assertSame($redis, $first);
        $this->assertSame($first, $second);
        $this->assertSame(1, $pool->getCount);
        $this->assertCount(1, $pool->putLog);
    }

    public function testDeadCheckoutIsDiscardedAndRetried(): void
    {
        $liveRedis = new FakeLiveRedis();
        $pool = new FakeRedisPool([new FakeDeadRedis(), $liveRedis]);
        $provider = new PooledRedisProvider($pool, 0.1);

        $checkedOut = null;
        Coroutine\run(static function () use ($provider, &$checkedOut): void {
            $checkedOut = $provider->get();
        });

        $this->assertSame($liveRedis, $checkedOut);
        // Dead slot freed with put(null); live connection returned on coroutine end.
        $this->assertSame([null, $liveRedis], $pool->putLog);
        $this->assertSame(2, $pool->getCount);
    }

    public function testSecondDeadCheckoutThrowsStaleWithDriverErrorAttached(): void
    {
        $pool = new FakeRedisPool([new FakeDeadRedis(), new FakeDeadRedis()]);
        $provider = new PooledRedisProvider($pool, 0.1);

        $caught = null;
        Coroutine\run(static function () use ($provider, &$caught): void {
            try {
                $provider->get();
            } catch (Throwable $e) {
                $caught = $e;
            }
        });

        $this->assertInstanceOf(StalePooledConnectionException::class, $caught);
        $this->assertInstanceOf(RedisException::class, $caught->getPrevious());
        // Both dead slots were freed.
        $this->assertSame([null, null], $pool->putLog);
    }

    public function testExhaustedPoolThrowsPoolTimeout(): void
    {
        $provider = new PooledRedisProvider(new FakeRedisPool([]), 0.1);

        $caught = null;
        Coroutine\run(static function () use ($provider, &$caught): void {
            try {
                $provider->get();
            } catch (Throwable $e) {
                $caught = $e;
            }
        });

        $this->assertInstanceOf(PoolTimeoutException::class, $caught);
    }

    public function testFailingReplacementDialOnDiscardIsSwallowed(): void
    {
        $liveRedis = new FakeLiveRedis();
        $pool = new FakeRedisPool([new FakeDeadRedis(), $liveRedis]);
        $pool->throwOnDiscard = true;
        $provider = new PooledRedisProvider($pool, 0.1);

        $checkedOut = null;
        Coroutine\run(static function () use ($provider, &$checkedOut): void {
            $checkedOut = $provider->get();
        });

        // put(null)'s failed replacement dial must not abort the retry.
        $this->assertSame($liveRedis, $checkedOut);
    }
}
