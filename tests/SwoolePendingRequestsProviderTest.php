<?php

declare(strict_types=1);

namespace BEAR\Async;

use BEAR\Async\Adapter\SyncAsync;
use BEAR\Async\Exception\NotInCoroutineException;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\TestCase;
use Swoole\Coroutine;

#[RequiresPhpExtension('swoole')]
final class SwoolePendingRequestsProviderTest extends TestCase
{
    public function testGetOutsideCoroutineThrows(): void
    {
        $provider = new SwoolePendingRequestsProvider(new SyncAsync());

        $this->expectException(NotInCoroutineException::class);

        $provider->get();
    }

    public function testGetReturnsSameInstanceWithinCoroutine(): void
    {
        $provider = new SwoolePendingRequestsProvider(new SyncAsync());

        $first = null;
        $second = null;
        Coroutine\run(static function () use ($provider, &$first, &$second): void {
            $first = $provider->get();
            $second = $provider->get();
        });

        $this->assertInstanceOf(PendingRequests::class, $first);
        $this->assertSame($first, $second);
    }

    public function testEachCoroutineGetsItsOwnInstance(): void
    {
        $provider = new SwoolePendingRequestsProvider(new SyncAsync());

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
    }
}
