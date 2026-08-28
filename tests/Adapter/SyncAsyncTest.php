<?php

declare(strict_types=1);

namespace BEAR\Async\Adapter;

use BEAR\Async\AsyncRequest;
use BEAR\Async\Fake\FakeInvoker;
use BEAR\Async\Fake\FakeResourceObject;
use BEAR\Async\Fake\FakeSwooleThrowingInvoker;
use BEAR\Async\PendingRequests;
use BEAR\Async\RequestTask;
use BEAR\Resource\Method;
use BEAR\Resource\Request;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Throwable;

class SyncAsyncTest extends TestCase
{
    private SyncAsync $syncAsync;

    protected function setUp(): void
    {
        $this->syncAsync = new SyncAsync();
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
        $ro = new FakeResourceObject('app://self/user');
        $ro->body = ['name' => 'Test'];
        $invoker = new FakeInvoker($ro);

        $request = new Request($invoker, $ro, Method::GET, []);

        $allRequests = new PendingRequests($this->syncAsync);
        $asyncRequest = new AsyncRequest($request, $allRequests);

        $key = $asyncRequest->hash();
        $results = $this->syncAsync->execute([$key => $asyncRequest]);

        $this->assertArrayHasKey($key, $results);
        $this->assertIsString($results[$key]);
    }

    public function testInvokeRunsAllTasksAndRethrowsFirstErrorInTaskOrder(): void
    {
        $failureOne = new RuntimeException('first failure');
        $taskFailOne = new RequestTask('hash-1', new Request(new FakeSwooleThrowingInvoker($failureOne), new FakeResourceObject('app://self/one'), Method::GET, []));

        $roTwo = new FakeResourceObject('app://self/two');
        $roTwo->body = ['name' => 'two'];
        $taskOk = new RequestTask('hash-2', new Request(new FakeInvoker($roTwo), $roTwo, Method::GET, []));

        $failureThree = new RuntimeException('second failure');
        $lastInvoker = new FakeSwooleThrowingInvoker($failureThree);
        $taskFailTwo = new RequestTask('hash-3', new Request($lastInvoker, new FakeResourceObject('app://self/three'), Method::GET, []));

        $caught = null;
        try {
            ($this->syncAsync)([
                'hash-1' => $taskFailOne,
                'hash-2' => $taskOk,
                'hash-3' => $taskFailTwo,
            ]);
        } catch (Throwable $e) {
            $caught = $e;
        }

        // Sibling tasks after the failure still ran to completion.
        $this->assertSame(['name' => 'two'], $taskOk->getResult());
        $this->assertSame(1, $lastInvoker->invokeCount);

        // The first failing task's throwable propagated, unchanged.
        $this->assertSame($failureOne, $caught);
    }

    public function testExecuteRunsAllRequestsAndRethrowsFirstErrorInRequestOrder(): void
    {
        $failureOne = new RuntimeException('first failure');
        $failureTwo = new RuntimeException('second failure');
        $okInvoker = new FakeInvoker(new FakeResourceObject('app://self/ok'));
        $lastInvoker = new FakeSwooleThrowingInvoker($failureTwo);

        $pendingRequests = new PendingRequests($this->syncAsync);
        $failRequestOne = new AsyncRequest(new Request(new FakeSwooleThrowingInvoker($failureOne), new FakeResourceObject('app://self/one'), Method::GET, []), $pendingRequests);
        $okRequest = new AsyncRequest(new Request($okInvoker, new FakeResourceObject('app://self/two'), Method::GET, []), $pendingRequests);
        $failRequestTwo = new AsyncRequest(new Request($lastInvoker, new FakeResourceObject('app://self/three'), Method::GET, []), $pendingRequests);

        $caught = null;
        try {
            $this->syncAsync->execute([
                'key-1' => $failRequestOne,
                'key-2' => $okRequest,
                'key-3' => $failRequestTwo,
            ]);
        } catch (Throwable $e) {
            $caught = $e;
        }

        // Sibling requests after the failure still ran to completion.
        $this->assertSame(1, $okInvoker->invokeCount);
        $this->assertSame(1, $lastInvoker->invokeCount);

        // The first failing request's throwable propagated, unchanged.
        $this->assertSame($failureOne, $caught);
    }
}
