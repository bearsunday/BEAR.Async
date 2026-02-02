<?php

declare(strict_types=1);

namespace BEAR\Async;

use BEAR\Async\Adapter\SyncAsync;
use BEAR\Async\Fake\FakeResourceObject;
use BEAR\Resource\InvokerInterface;
use BEAR\Resource\Method;
use BEAR\Resource\Request;
use PHPUnit\Framework\TestCase;

class AsyncRequestTest extends TestCase
{
    public function testUri(): void
    {
        $invoker = $this->createMock(InvokerInterface::class);
        $ro = new FakeResourceObject('app://self/user');
        $request = new Request($invoker, $ro, Method::GET, []);

        $allRequests = new PendingRequests(new SyncAsync());
        $asyncRequest = new AsyncRequest($request, $allRequests);

        $this->assertSame('app://self/user', $asyncRequest->uri);
    }

    public function testInvokeReturnsResourceObject(): void
    {
        $invoker = $this->createMock(InvokerInterface::class);
        $ro = new FakeResourceObject('app://self/user');
        $ro->body = ['name' => 'Test'];
        $invoker->method('invoke')
            ->willReturn($ro);

        $request = new Request($invoker, $ro, Method::GET, []);

        $pendingRequests = new PendingRequests(new SyncAsync());
        $asyncRequest = new AsyncRequest($request, $pendingRequests);

        $result = $asyncRequest();

        $this->assertInstanceOf(FakeResourceObject::class, $result);
        $this->assertSame(['name' => 'Test'], $result->body);
    }

    public function testQuery(): void
    {
        $invoker = $this->createMock(InvokerInterface::class);
        $ro = new FakeResourceObject('app://self/user');
        $query = ['id' => '123', 'name' => 'test'];
        $request = new Request($invoker, $ro, Method::GET, $query);

        $pendingRequests = new PendingRequests(new SyncAsync());
        $asyncRequest = new AsyncRequest($request, $pendingRequests);

        $this->assertSame($query, $asyncRequest->query);
    }

    public function testToStringTriggersExecution(): void
    {
        $invoker = $this->createMock(InvokerInterface::class);
        $ro = new FakeResourceObject('app://self/user');
        $ro->body = ['name' => 'Test'];
        $invoker->method('invoke')
            ->willReturn($ro);

        $request = new Request($invoker, $ro, Method::GET, []);

        $allRequests = new PendingRequests(new SyncAsync());
        $asyncRequest = new AsyncRequest($request, $allRequests);

        // When __toString is called, execution should happen
        $result = (string) $asyncRequest;

        $this->assertNotEmpty($result);
    }
}
