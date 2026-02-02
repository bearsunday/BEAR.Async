<?php

declare(strict_types=1);

namespace BEAR\Async\Adapter;

use BEAR\Async\PendingRequests;
use BEAR\Async\AsyncRequest;
use BEAR\Async\Fake\FakeResourceObject;
use BEAR\Resource\InvokerInterface;
use BEAR\Resource\Request;
use PHPUnit\Framework\TestCase;

class SyncAsyncTest extends TestCase
{
    private SyncAsync $syncAsync;

    protected function setUp(): void
    {
        $this->syncAsync = new SyncAsync();
    }

    public function testIsAvailable(): void
    {
        $this->assertTrue($this->syncAsync->isAvailable());
    }

    public function testInvokeWithEmptyTasks(): void
    {
        $this->expectNotToPerformAssertions();
        ($this->syncAsync)([]);
    }

    public function testExecuteWithEmptyRequests(): void
    {
        $results = $this->syncAsync->execute([]);
        $this->assertSame([], $results);
    }

    public function testExecuteReturnsRenderedViews(): void
    {
        $invoker = $this->createMock(InvokerInterface::class);
        $ro = new FakeResourceObject('app://self/user');
        $ro->body = ['name' => 'Test'];
        $invoker->method('invoke')
            ->willReturn($ro);

        $request = new Request($invoker, $ro, 'get', []);

        $allRequests = new PendingRequests($this->syncAsync);
        $asyncRequest = new AsyncRequest($request, $allRequests);

        $results = $this->syncAsync->execute(['app://self/user' => $asyncRequest]);

        $this->assertArrayHasKey('app://self/user', $results);
        $this->assertIsString($results['app://self/user']);
    }
}
