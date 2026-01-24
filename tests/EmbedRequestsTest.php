<?php

declare(strict_types=1);

namespace BEAR\Async;

use BEAR\Resource\InvokerInterface;
use BEAR\Resource\Request;
use BEAR\Resource\ResourceObject;
use PHPUnit\Framework\TestCase;

class EmbedRequestsTest extends TestCase
{
    public function testAdd(): void
    {
        $invoker = $this->createMock(InvokerInterface::class);
        $ro = $this->createMock(ResourceObject::class);
        $request = new Request($invoker, $ro, 'get', []);

        $embedRequests = new EmbedRequests();
        $future = $embedRequests->add($request);

        $this->assertInstanceOf(FutureResource::class, $future);
        $this->assertSame($request, $future->getRequest());
    }

    public function testHasPendingInitiallyFalse(): void
    {
        $embedRequests = new EmbedRequests();

        $this->assertFalse($embedRequests->hasPending());
    }

    public function testHasPendingAfterAdd(): void
    {
        $invoker = $this->createMock(InvokerInterface::class);
        $ro = $this->createMock(ResourceObject::class);
        $request = new Request($invoker, $ro, 'get', []);

        $embedRequests = new EmbedRequests();
        $embedRequests->add($request);

        $this->assertTrue($embedRequests->hasPending());
    }

    public function testCount(): void
    {
        $invoker = $this->createMock(InvokerInterface::class);
        $ro = $this->createMock(ResourceObject::class);
        $request1 = new Request($invoker, $ro, 'get', []);
        $request2 = new Request($invoker, $ro, 'get', []);

        $embedRequests = new EmbedRequests();

        $this->assertSame(0, $embedRequests->count());

        $embedRequests->add($request1);
        $this->assertSame(1, $embedRequests->count());

        $embedRequests->add($request2);
        $this->assertSame(2, $embedRequests->count());
    }

    public function testDrain(): void
    {
        $invoker = $this->createMock(InvokerInterface::class);
        $ro = $this->createMock(ResourceObject::class);
        $request1 = new Request($invoker, $ro, 'get', []);
        $request2 = new Request($invoker, $ro, 'get', []);

        $embedRequests = new EmbedRequests();
        $embedRequests->add($request1);
        $embedRequests->add($request2);

        $futures = $embedRequests->drain();

        $this->assertCount(2, $futures);
        $this->assertContainsOnlyInstancesOf(FutureResource::class, $futures);
    }

    public function testDrainClearsCollection(): void
    {
        $invoker = $this->createMock(InvokerInterface::class);
        $ro = $this->createMock(ResourceObject::class);
        $request = new Request($invoker, $ro, 'get', []);

        $embedRequests = new EmbedRequests();
        $embedRequests->add($request);

        $embedRequests->drain();

        $this->assertFalse($embedRequests->hasPending());
        $this->assertSame(0, $embedRequests->count());
    }

    public function testDrainReturnsEmptyArrayWhenEmpty(): void
    {
        $embedRequests = new EmbedRequests();

        $futures = $embedRequests->drain();

        $this->assertSame([], $futures);
    }

    public function testMultipleDrainCalls(): void
    {
        $invoker = $this->createMock(InvokerInterface::class);
        $ro = $this->createMock(ResourceObject::class);
        $request1 = new Request($invoker, $ro, 'get', []);
        $request2 = new Request($invoker, $ro, 'get', []);

        $embedRequests = new EmbedRequests();
        $embedRequests->add($request1);

        $first = $embedRequests->drain();

        $embedRequests->add($request2);

        $second = $embedRequests->drain();

        $this->assertCount(1, $first);
        $this->assertCount(1, $second);
    }
}
