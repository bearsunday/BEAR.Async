<?php

declare(strict_types=1);

namespace BEAR\Async;

use BEAR\Async\Adapter\SyncAsync;
use BEAR\Async\Fake\FakeResourceObject;
use BEAR\Resource\InvokerInterface;
use BEAR\Resource\Request;
use BEAR\Resource\ResourceObject;
use PHPUnit\Framework\TestCase;

class PendingRequestsTest extends TestCase
{
    public function testAddAndGetResult(): void
    {
        // Create a mock invoker that returns a rendered resource
        $invoker = $this->createMock(InvokerInterface::class);
        $ro = new FakeResourceObject('app://self/user');
        $ro->body = ['name' => 'Test'];
        $invoker->method('invoke')
            ->willReturn($ro);

        $request = new Request($invoker, $ro, 'get', []);

        $allRequests = new PendingRequests(new SyncAsync());
        $asyncRequest = new AsyncRequest($request, $allRequests);

        // When we call __toString on AsyncRequest, it should get the result
        $result = (string) $asyncRequest;

        $this->assertNotEmpty($result);
    }

    public function testDeduplicatesRequests(): void
    {
        $invoker = $this->createMock(InvokerInterface::class);
        $ro = new FakeResourceObject('app://self/user');
        $ro->body = ['name' => 'Test'];
        $invoker->method('invoke')
            ->willReturn($ro);

        $request1 = new Request($invoker, $ro, 'get', []);
        $request2 = new Request($invoker, $ro, 'get', []);

        $allRequests = new PendingRequests(new SyncAsync());

        // Both requests have the same URI
        $asyncRequest1 = new AsyncRequest($request1, $allRequests);
        $asyncRequest2 = new AsyncRequest($request2, $allRequests);

        // Should return the same result for the same URI
        $result1 = (string) $asyncRequest1;
        $result2 = (string) $asyncRequest2;

        $this->assertSame($result1, $result2);
    }

    public function testResetClearsCache(): void
    {
        $invoker = $this->createMock(InvokerInterface::class);
        $ro = new FakeResourceObject('app://self/user');
        $ro->body = ['name' => 'Test'];
        $invoker->method('invoke')
            ->willReturn($ro);

        $request = new Request($invoker, $ro, 'get', []);

        $pendingRequests = new PendingRequests(new SyncAsync());
        $asyncRequest = new AsyncRequest($request, $pendingRequests);

        // First request - populates cache
        (string) $asyncRequest;

        // Reset clears the cache
        $pendingRequests->reset();

        // After reset, a new request with same URI should be added to pending
        $asyncRequest2 = new AsyncRequest($request, $pendingRequests);
        $result = (string) $asyncRequest2;

        $this->assertNotEmpty($result);
    }

    public function testCachesResults(): void
    {
        $invoker = $this->createMock(InvokerInterface::class);
        $ro = new FakeResourceObject('app://self/user');
        $ro->body = ['name' => 'Test'];

        // The invoker should only be called once due to caching
        $invoker->expects($this->once())
            ->method('invoke')
            ->willReturn($ro);

        $request = new Request($invoker, $ro, 'get', []);

        $allRequests = new PendingRequests(new SyncAsync());
        $asyncRequest = new AsyncRequest($request, $allRequests);

        // Get result twice - invoke should only happen once
        $result1 = (string) $asyncRequest;
        $result2 = (string) $asyncRequest;

        $this->assertSame($result1, $result2);
    }

    public function testDifferentQueriesAreNotDeduplicated(): void
    {
        $invoker = $this->createMock(InvokerInterface::class);
        $ro = new FakeResourceObject('app://self/user');
        $ro->body = ['name' => 'Test'];

        // Invoker should be called twice for different queries
        $invoker->expects($this->exactly(2))
            ->method('invoke')
            ->willReturn($ro);

        $request1 = new Request($invoker, $ro, 'get', ['id' => '1']);
        $request2 = new Request($invoker, $ro, 'get', ['id' => '2']);

        $pendingRequests = new PendingRequests(new SyncAsync());
        $asyncRequest1 = new AsyncRequest($request1, $pendingRequests);
        $asyncRequest2 = new AsyncRequest($request2, $pendingRequests);

        // Different URIs (due to different query params) should both execute
        (string) $asyncRequest1;
        (string) $asyncRequest2;

        // Verify URIs are different
        $this->assertNotSame($asyncRequest1->uri, $asyncRequest2->uri);
    }
}
