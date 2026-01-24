<?php

declare(strict_types=1);

namespace BEAR\Async;

use BEAR\Async\Fake\FakeResourceObject;
use BEAR\Resource\InvokerInterface;
use BEAR\Resource\Request;
use PHPUnit\Framework\TestCase;

class EmbedDataLoaderTest extends TestCase
{
    public function testLoadWithEmptyRequests(): void
    {
        $async = $this->createMock(AsyncInterface::class);
        $async->expects($this->never())->method('__invoke');

        $loader = new EmbedDataLoader($async);
        $requests = new EmbedRequests();

        $loader->load($requests);

        // No exceptions, test passes
        $this->assertTrue(true);
    }

    public function testLoadFallsBackToSequentialWhenAsyncNotAvailable(): void
    {
        $resultRo = new FakeResourceObject();
        $resultRo->body = ['key' => 'value'];

        $invoker = $this->createMock(InvokerInterface::class);
        $invoker->method('invoke')->willReturn($resultRo);

        $ro = new FakeResourceObject('app://self/test');
        $request = new Request($invoker, $ro, 'get', []);

        $async = $this->createMock(AsyncInterface::class);
        $async->method('isAvailable')->willReturn(false);
        $async->expects($this->never())->method('__invoke');

        $loader = new EmbedDataLoader($async);
        $requests = new EmbedRequests();
        $future = $requests->add($request);

        $loader->load($requests);

        // Future should be resolved via sequential execution
        $this->assertTrue($future->isResolved());
        $this->assertSame(['key' => 'value'], $future->await()->body);
    }

    public function testLoadUsesAsyncInterfaceWhenAvailable(): void
    {
        $invoker = $this->createMock(InvokerInterface::class);

        $ro = new FakeResourceObject('app://self/test');
        $request = new Request($invoker, $ro, 'get', []);

        $async = $this->createMock(AsyncInterface::class);
        $async->method('isAvailable')->willReturn(true);
        $async->expects($this->once())
            ->method('__invoke')
            ->willReturnCallback(function (array $tasks): void {
                foreach ($tasks as $task) {
                    $task->setResult(['async' => 'result']);
                }
            });

        $loader = new EmbedDataLoader($async);
        $requests = new EmbedRequests();
        $future = $requests->add($request);

        $loader->load($requests);

        // Future should be resolved via async execution
        $this->assertTrue($future->isResolved());
        $result = $future->await();
        $this->assertSame(['async' => 'result'], $result->body);
    }

    public function testLoadMultipleFuturesInParallel(): void
    {
        $invoker = $this->createMock(InvokerInterface::class);

        $ro = new FakeResourceObject('app://self/test');
        $request1 = new Request($invoker, $ro, 'get', []);
        $request2 = new Request($invoker, $ro, 'get', []);

        $async = $this->createMock(AsyncInterface::class);
        $async->method('isAvailable')->willReturn(true);
        $async->expects($this->once())
            ->method('__invoke')
            ->willReturnCallback(function (array $tasks): void {
                $i = 1;
                foreach ($tasks as $task) {
                    $task->setResult(['result' => $i++]);
                }
            });

        $loader = new EmbedDataLoader($async);
        $requests = new EmbedRequests();
        $future1 = $requests->add($request1);
        $future2 = $requests->add($request2);

        $loader->load($requests);

        $this->assertTrue($future1->isResolved());
        $this->assertTrue($future2->isResolved());
        $this->assertSame(['result' => 1], $future1->await()->body);
        $this->assertSame(['result' => 2], $future2->await()->body);
    }

    public function testLoadPreservesUriInResolvedResource(): void
    {
        $invoker = $this->createMock(InvokerInterface::class);

        $ro = new FakeResourceObject('app://self/users');
        $request = new Request($invoker, $ro, 'get', []);

        $async = $this->createMock(AsyncInterface::class);
        $async->method('isAvailable')->willReturn(true);
        $async->expects($this->once())
            ->method('__invoke')
            ->willReturnCallback(function (array $tasks): void {
                foreach ($tasks as $task) {
                    $task->setResult(['name' => 'John']);
                }
            });

        $loader = new EmbedDataLoader($async);
        $requests = new EmbedRequests();
        $future = $requests->add($request);

        $loader->load($requests);

        $result = $future->await();
        $this->assertSame('app://self/users', (string) $result->uri);
    }

    public function testLoadHandlesExceptionInSequentialMode(): void
    {
        $invoker = $this->createMock(InvokerInterface::class);
        $invoker->method('invoke')->willThrowException(new \RuntimeException('Test error'));

        $ro = new FakeResourceObject('app://self/test');
        $request = new Request($invoker, $ro, 'get', []);

        $async = $this->createMock(AsyncInterface::class);
        $async->method('isAvailable')->willReturn(false);

        $loader = new EmbedDataLoader($async);
        $requests = new EmbedRequests();
        $future = $requests->add($request);

        $loader->load($requests);

        // Future should be resolved with error resource
        $this->assertTrue($future->isResolved());
        $result = $future->await();
        $this->assertSame(500, $result->code);
        $this->assertSame('Test error', $result->body['error']);
    }
}
