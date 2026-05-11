<?php

declare(strict_types=1);

namespace BEAR\Async;

use BEAR\Async\Adapter\SyncAsync;
use BEAR\Async\Fake\FakeResourceObject;
use BEAR\Resource\AbstractRequest;
use BEAR\Resource\InvokerInterface;
use BEAR\Resource\Method;
use BEAR\Resource\Request;
use PHPUnit\Framework\TestCase;

class AsyncRequestTest extends TestCase
{
    public function testIsAbstractRequest(): void
    {
        $invoker = $this->createMock(InvokerInterface::class);
        $ro = new FakeResourceObject('app://self/user');
        $request = new Request($invoker, $ro, Method::GET, []);

        $allRequests = new PendingRequests(new SyncAsync());
        $asyncRequest = new AsyncRequest($request, $allRequests);

        // Renderers that walk AbstractRequest instances (HalRenderer, ResourceDonut)
        // must see AsyncRequest as a request so __toString() is invoked.
        $this->assertInstanceOf(AbstractRequest::class, $asyncRequest);
    }

    public function testUri(): void
    {
        $invoker = $this->createMock(InvokerInterface::class);
        $ro = new FakeResourceObject('app://self/user');
        $request = new Request($invoker, $ro, Method::GET, []);

        $allRequests = new PendingRequests(new SyncAsync());
        $asyncRequest = new AsyncRequest($request, $allRequests);

        $this->assertSame('app://self/user', $asyncRequest->toUri());
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

    public function testWithQueryRekeysPendingRequest(): void
    {
        $invoker = $this->createMock(InvokerInterface::class);
        $ro = new FakeResourceObject('app://self/user');
        $invoker->method('invoke')->willReturn($ro);
        $request = new Request($invoker, $ro, Method::GET, []);

        $pendingRequests = new PendingRequests(new SyncAsync());
        $asyncRequest = new AsyncRequest($request, $pendingRequests);

        // Mutate after registration — URI changes; PendingRequests must
        // re-key the entry so __toString() finds it.
        $asyncRequest->withQuery(['id' => '7']);

        $this->assertSame('app://self/user?id=7', $asyncRequest->toUri());
        // Should not raise ResultNotFoundException.
        $this->assertNotEmpty((string) $asyncRequest);
    }

    public function testAddQueryRekeysPendingRequest(): void
    {
        $invoker = $this->createMock(InvokerInterface::class);
        $ro = new FakeResourceObject('app://self/user');
        $invoker->method('invoke')->willReturn($ro);
        $request = new Request($invoker, $ro, Method::GET, ['id' => '1']);

        $pendingRequests = new PendingRequests(new SyncAsync());
        $asyncRequest = new AsyncRequest($request, $pendingRequests);

        $asyncRequest->addQuery(['name' => 'alice']);

        $this->assertStringContainsString('id=1', $asyncRequest->toUri());
        $this->assertStringContainsString('name=alice', $asyncRequest->toUri());
        $this->assertNotEmpty((string) $asyncRequest);
    }

    public function testToStringReturnsPendingRequestsResult(): void
    {
        $invoker = $this->createMock(InvokerInterface::class);
        $ro = new FakeResourceObject('app://self/article');
        $ro->body = ['title' => 'hello'];
        $invoker->method('invoke')
            ->willReturn($ro);

        $request = new Request($invoker, $ro, Method::GET, []);

        $async = new class implements AsyncInterface {
            /** {@inheritDoc} */
            public function __invoke(array $tasks): void
            {
            }

            /** {@inheritDoc} */
            public function execute(array $requests): array
            {
                $results = [];
                foreach ($requests as $uri => $request) {
                    $results[$uri] = '<rendered:' . $uri . '>';
                }

                return $results;
            }

            public function isAvailable(): bool
            {
                return true;
            }
        };

        $pendingRequests = new PendingRequests($async);
        $asyncRequest = new AsyncRequest($request, $pendingRequests);

        $this->assertSame('<rendered:app://self/article>', (string) $asyncRequest);
    }
}
