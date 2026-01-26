<?php

declare(strict_types=1);

namespace BEAR\Async;

use BEAR\Async\Fake\FakeResourceObject;
use BEAR\Resource\InvokerInterface;
use BEAR\Resource\RenderInterface;
use BEAR\Resource\Request;
use PHPUnit\Framework\TestCase;

class AsyncRenderDecoratorTest extends TestCase
{
    public function testRenderDelegatesToInnerRenderer(): void
    {
        $innerRenderer = $this->createMock(RenderInterface::class);
        $innerRenderer->expects($this->once())
            ->method('render')
            ->willReturn('{"result": "test"}');

        $async = $this->createMock(AsyncInterface::class);
        $async->method('isAvailable')->willReturn(false);

        $decorator = new AsyncRenderDecorator($innerRenderer, $async);
        $ro = new FakeResourceObject();

        $result = $decorator->render($ro);

        $this->assertSame('{"result": "test"}', $result);
    }

    public function testRenderWithNoEmbeddedRequests(): void
    {
        $innerRenderer = $this->createMock(RenderInterface::class);
        $innerRenderer->expects($this->once())
            ->method('render')
            ->willReturn('{}');

        $async = $this->createMock(AsyncInterface::class);
        $async->method('isAvailable')->willReturn(true);
        $async->expects($this->never())->method('__invoke');

        $decorator = new AsyncRenderDecorator($innerRenderer, $async);
        $ro = new FakeResourceObject();
        $ro->body = ['key' => 'value']; // No AbstractRequest

        $result = $decorator->render($ro);

        $this->assertSame('{}', $result);
    }

    public function testRenderCollectsAndExecutesEmbeddedRequests(): void
    {
        $invoker = $this->createMock(InvokerInterface::class);
        $embeddedRo = new FakeResourceObject('app://self/embedded');
        $request = new Request($invoker, $embeddedRo, 'get', []);

        $innerRenderer = $this->createMock(RenderInterface::class);
        $innerRenderer->expects($this->once())
            ->method('render')
            ->willReturn('{}');

        $async = $this->createMock(AsyncInterface::class);
        $async->method('isAvailable')->willReturn(true);
        $async->expects($this->once())
            ->method('__invoke')
            ->willReturnCallback(function (array $tasks): void {
                $this->assertCount(1, $tasks);
                $this->assertArrayHasKey('embed_0', $tasks);
                $task = $tasks['embed_0'];
                $this->assertInstanceOf(EmbedTask::class, $task);
            });

        $decorator = new AsyncRenderDecorator($innerRenderer, $async);
        $ro = new FakeResourceObject();
        $ro->body = ['embedded' => $request];

        $decorator->render($ro);
    }

    public function testRenderDoesNotExecuteWhenAsyncNotAvailable(): void
    {
        $invoker = $this->createMock(InvokerInterface::class);
        $embeddedRo = new FakeResourceObject('app://self/embedded');
        $request = new Request($invoker, $embeddedRo, 'get', []);

        $innerRenderer = $this->createMock(RenderInterface::class);
        $innerRenderer->expects($this->once())
            ->method('render')
            ->willReturn('{}');

        $async = $this->createMock(AsyncInterface::class);
        $async->method('isAvailable')->willReturn(false);
        $async->expects($this->never())->method('__invoke');

        $decorator = new AsyncRenderDecorator($innerRenderer, $async);
        $ro = new FakeResourceObject();
        $ro->body = ['embedded' => $request];

        $decorator->render($ro);
    }

    public function testRenderWithScalarBody(): void
    {
        $innerRenderer = $this->createMock(RenderInterface::class);
        $innerRenderer->expects($this->once())
            ->method('render')
            ->willReturn('"string"');

        $async = $this->createMock(AsyncInterface::class);
        $async->method('isAvailable')->willReturn(true);
        $async->expects($this->never())->method('__invoke');

        $decorator = new AsyncRenderDecorator($innerRenderer, $async);
        $ro = new FakeResourceObject();
        $ro->body = 'string'; // @phpstan-ignore assign.propertyType

        $result = $decorator->render($ro);

        $this->assertSame('"string"', $result);
    }

    public function testRenderPopulatesCacheForParallelExecution(): void
    {
        $invoker = $this->createMock(InvokerInterface::class);
        $embeddedRo = new FakeResourceObject('app://self/embedded');
        $request = new Request($invoker, $embeddedRo, 'get', []);

        $innerRenderer = $this->createMock(RenderInterface::class);
        $innerRenderer->expects($this->once())
            ->method('render')
            ->willReturn('{}');

        $async = $this->createMock(AsyncInterface::class);
        $async->method('isAvailable')->willReturn(true);
        $async->expects($this->once())
            ->method('__invoke')
            ->willReturnCallback(function (array $tasks): void {
                // Simulate ParallelAsync behavior: set result but don't populate cache
                foreach ($tasks as $task) {
                    $task->setResult(['data' => 'value']);
                }
            });

        $decorator = new AsyncRenderDecorator($innerRenderer, $async);
        $ro = new FakeResourceObject();
        $ro->body = ['embedded' => $request];

        $decorator->render($ro);

        // Verify that the cache was populated via reflection
        $resultProperty = new \ReflectionProperty(\BEAR\Resource\AbstractRequest::class, 'result');
        $cachedResult = $resultProperty->getValue($request);

        $this->assertInstanceOf(\BEAR\Resource\ResourceObject::class, $cachedResult);
        $this->assertSame(['data' => 'value'], $cachedResult->body);
    }
}
