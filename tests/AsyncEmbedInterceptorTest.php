<?php

declare(strict_types=1);

namespace BEAR\Async;

use BEAR\Async\Adapter\SyncAsync;
use BEAR\Async\Fake\AsyncEmbedResource;
use BEAR\Async\Fake\FakeInvoker;
use BEAR\Async\Fake\FakeMethodInvocation;
use BEAR\Async\Fake\FakeResource;
use BEAR\Async\Fake\FakeResourceObject;
use BEAR\Resource\Method;
use BEAR\Resource\Request;
use BEAR\Resource\ResourceInterface;
use BEAR\Resource\ResourceObject;
use PHPUnit\Framework\TestCase;
use Ray\Aop\ReflectionMethod;
use Ray\Di\ProviderInterface;

class AsyncEmbedInterceptorTest extends TestCase
{
    public function testWrapsEmbedRequestBeforeProceed(): void
    {
        $mainRo = new FakeResourceObject('app://self/article');
        $resource = new FakeResource();

        $interceptor = new AsyncEmbedInterceptor($this->newProvider($resource), new PendingRequests(new SyncAsync()));
        $invocation = $this->newInvocation($mainRo, new ReflectionMethod(AsyncEmbedResource::class, 'onGet'));
        $invocation->proceed = function () use ($mainRo): ResourceObject {
                $this->assertIsArray($mainRo->body);
                $this->assertInstanceOf(AsyncRequest::class, $mainRo->body['embedded']);

                return $mainRo;
        };

        $result = $interceptor->invoke($invocation);

        $this->assertSame([], $resource->newRequests);
        $this->assertSame(1, $invocation->proceedCount);
        $this->assertInstanceOf(ResourceObject::class, $result);
        $this->assertIsArray($result->body);
        $this->assertInstanceOf(AsyncRequest::class, $result->body['embedded']);
        (string) $result->body['embedded'];
        $this->assertSame([['method' => Method::GET, 'uri' => 'app://self/embedded', 'query' => []]], $resource->newRequests);
    }

    public function testDoesNotModifyNonArrayBody(): void
    {
        $mainRo = new FakeResourceObject();
        $mainRo->body = 'string body'; // @phpstan-ignore assign.propertyType
        $resource = new FakeResource();

        $interceptor = new AsyncEmbedInterceptor($this->newProvider($resource), new PendingRequests(new SyncAsync()));
        $invocation = $this->newInvocation($mainRo, new ReflectionMethod(AsyncEmbedResource::class, 'withoutEmbed'));
        $invocation->proceed = static fn (): ResourceObject => $mainRo;

        $result = $interceptor->invoke($invocation);

        $this->assertInstanceOf(ResourceObject::class, $result);
        $this->assertSame('string body', $result->body);
    }

    public function testDoesNotModifyNonRequestValues(): void
    {
        $mainRo = new FakeResourceObject();
        $mainRo->body = ['key' => 'value', 'number' => 42];
        $resource = new FakeResource();

        $interceptor = new AsyncEmbedInterceptor($this->newProvider($resource), new PendingRequests(new SyncAsync()));
        $invocation = $this->newInvocation($mainRo, new ReflectionMethod(AsyncEmbedResource::class, 'withoutEmbed'));
        $invocation->proceed = static fn (): ResourceObject => $mainRo;

        $result = $interceptor->invoke($invocation);

        $this->assertInstanceOf(ResourceObject::class, $result);
        $this->assertSame('value', $result->body['key']);
        $this->assertSame(42, $result->body['number']);
    }

    public function testWrapsMultipleEmbedRequests(): void
    {
        $mainRo = new FakeResourceObject('app://self/article');
        $resource = new FakeResource();

        $interceptor = new AsyncEmbedInterceptor($this->newProvider($resource), new PendingRequests(new SyncAsync()));
        $invocation = $this->newInvocation($mainRo, new ReflectionMethod(AsyncEmbedResource::class, 'withMultipleEmbeds'));
        $invocation->proceed = static function () use ($mainRo): ResourceObject {
            $mainRo->body += ['title' => 'Page Title'];

            return $mainRo;
        };

        $result = $interceptor->invoke($invocation);

        $this->assertInstanceOf(ResourceObject::class, $result);
        $this->assertIsArray($result->body);
        $this->assertInstanceOf(AsyncRequest::class, $result->body['user']);
        $this->assertInstanceOf(AsyncRequest::class, $result->body['posts']);
        $this->assertSame('Page Title', $result->body['title']);
    }

    public function testWrapsRequestCreatedDuringProceed(): void
    {
        $mainRo = new FakeResourceObject('app://self/article');
        $resource = new FakeResource();
        $request = $this->newRequest('app://self/late');

        $interceptor = new AsyncEmbedInterceptor($this->newProvider($resource), new PendingRequests(new SyncAsync()));
        $invocation = $this->newInvocation($mainRo, new ReflectionMethod(AsyncEmbedResource::class, 'withoutEmbed'));
        $invocation->proceed = static function () use ($mainRo, $request): ResourceObject {
            $mainRo->body = ['late' => $request];

            return $mainRo;
        };

        $result = $interceptor->invoke($invocation);

        $this->assertInstanceOf(ResourceObject::class, $result);
        $this->assertIsArray($result->body);
        $this->assertInstanceOf(AsyncRequest::class, $result->body['late']);
    }

    private function newRequest(string $uri): Request
    {
        $ro = new FakeResourceObject($uri);
        $invoker = new FakeInvoker($ro);

        return new Request($invoker, $ro, Method::GET, []);
    }

    /** @return ProviderInterface<ResourceInterface> */
    private function newProvider(FakeResource $resource): ProviderInterface
    {
        return new class ($resource) implements ProviderInterface {
            public function __construct(
                private readonly FakeResource $resource,
            ) {
            }

            public function get(): FakeResource
            {
                return $this->resource;
            }
        };
    }

    private function newInvocation(ResourceObject $ro, ReflectionMethod $method): FakeMethodInvocation
    {
        return new FakeMethodInvocation($ro, $method, arguments: []);
    }
}
