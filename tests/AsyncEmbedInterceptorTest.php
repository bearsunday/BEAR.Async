<?php

declare(strict_types=1);

namespace BEAR\Async;

use BEAR\Async\Adapter\SyncAsync;
use BEAR\Async\Fake\FakeResourceObject;
use BEAR\Resource\AbstractRequest;
use BEAR\Resource\InvokerInterface;
use BEAR\Resource\Request;
use BEAR\Resource\ResourceObject;
use PHPUnit\Framework\TestCase;
use Ray\Aop\MethodInterceptor;
use Ray\Aop\MethodInvocation;

class AsyncEmbedInterceptorTest extends TestCase
{
    public function testWrapsAbstractRequestWithAsyncRequest(): void
    {
        $invoker = $this->createMock(InvokerInterface::class);
        $embeddedRo = new FakeResourceObject('app://self/embedded');
        $request = new Request($invoker, $embeddedRo, 'get', []);

        $mainRo = new FakeResourceObject();
        $mainRo->body = ['embedded' => $request];

        $innerInterceptor = $this->createMock(MethodInterceptor::class);
        $innerInterceptor->method('invoke')
            ->willReturn($mainRo);

        $allRequests = new PendingRequests(new SyncAsync());
        $interceptor = new AsyncEmbedInterceptor($innerInterceptor, $allRequests);

        $invocation = $this->createMock(MethodInvocation::class);
        $result = $interceptor->invoke($invocation);

        $this->assertInstanceOf(ResourceObject::class, $result);
        $this->assertIsArray($result->body);
        $this->assertArrayHasKey('embedded', $result->body);
        $this->assertInstanceOf(AsyncRequest::class, $result->body['embedded']);
    }

    public function testDoesNotModifyNonArrayBody(): void
    {
        $mainRo = new FakeResourceObject();
        $mainRo->body = 'string body'; // @phpstan-ignore assign.propertyType

        $innerInterceptor = $this->createMock(MethodInterceptor::class);
        $innerInterceptor->method('invoke')
            ->willReturn($mainRo);

        $allRequests = new PendingRequests(new SyncAsync());
        $interceptor = new AsyncEmbedInterceptor($innerInterceptor, $allRequests);

        $invocation = $this->createMock(MethodInvocation::class);
        $result = $interceptor->invoke($invocation);

        $this->assertSame('string body', $result->body);
    }

    public function testDoesNotModifyNonRequestValues(): void
    {
        $mainRo = new FakeResourceObject();
        $mainRo->body = ['key' => 'value', 'number' => 42];

        $innerInterceptor = $this->createMock(MethodInterceptor::class);
        $innerInterceptor->method('invoke')
            ->willReturn($mainRo);

        $allRequests = new PendingRequests(new SyncAsync());
        $interceptor = new AsyncEmbedInterceptor($innerInterceptor, $allRequests);

        $invocation = $this->createMock(MethodInvocation::class);
        $result = $interceptor->invoke($invocation);

        $this->assertSame('value', $result->body['key']);
        $this->assertSame(42, $result->body['number']);
    }

    public function testWrapsMultipleRequests(): void
    {
        $invoker = $this->createMock(InvokerInterface::class);
        $embeddedRo1 = new FakeResourceObject('app://self/user');
        $embeddedRo2 = new FakeResourceObject('app://self/posts');
        $request1 = new Request($invoker, $embeddedRo1, 'get', []);
        $request2 = new Request($invoker, $embeddedRo2, 'get', []);

        $mainRo = new FakeResourceObject();
        $mainRo->body = [
            'user' => $request1,
            'posts' => $request2,
            'title' => 'Page Title',
        ];

        $innerInterceptor = $this->createMock(MethodInterceptor::class);
        $innerInterceptor->method('invoke')
            ->willReturn($mainRo);

        $allRequests = new PendingRequests(new SyncAsync());
        $interceptor = new AsyncEmbedInterceptor($innerInterceptor, $allRequests);

        $invocation = $this->createMock(MethodInvocation::class);
        $result = $interceptor->invoke($invocation);

        $this->assertInstanceOf(AsyncRequest::class, $result->body['user']);
        $this->assertInstanceOf(AsyncRequest::class, $result->body['posts']);
        $this->assertSame('Page Title', $result->body['title']);
    }
}
