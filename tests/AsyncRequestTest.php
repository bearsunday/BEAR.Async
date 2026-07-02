<?php

declare(strict_types=1);

namespace BEAR\Async;

use BEAR\Async\Adapter\SyncAsync;
use BEAR\Async\Fake\FakeInvoker;
use BEAR\Async\Fake\FakePendingResourceProvider;
use BEAR\Async\Fake\FakeResource;
use BEAR\Async\Fake\FakeResourceObject;
use BEAR\Resource\AbstractRequest;
use BEAR\Resource\Method;
use BEAR\Resource\Request;
use PHPUnit\Framework\TestCase;

use function json_encode;

use const JSON_THROW_ON_ERROR;

class AsyncRequestTest extends TestCase
{
    public function testIsAbstractRequest(): void
    {
        $ro = new FakeResourceObject('app://self/user');
        $invoker = new FakeInvoker($ro);
        $request = new Request($invoker, $ro, Method::GET, []);

        $allRequests = new PendingRequests(new SyncAsync());
        $asyncRequest = new AsyncRequest($request, $allRequests);

        // Renderers that walk AbstractRequest instances (HalRenderer, ResourceDonut)
        // must see AsyncRequest as a request so __toString() is invoked.
        $this->assertInstanceOf(AbstractRequest::class, $asyncRequest);
    }

    public function testUri(): void
    {
        $ro = new FakeResourceObject('app://self/user');
        $invoker = new FakeInvoker($ro);
        $request = new Request($invoker, $ro, Method::GET, []);

        $allRequests = new PendingRequests(new SyncAsync());
        $asyncRequest = new AsyncRequest($request, $allRequests);

        $this->assertSame('app://self/user', $asyncRequest->toUri());
    }

    public function testInvokeReturnsResourceObject(): void
    {
        $ro = new FakeResourceObject('app://self/user');
        $ro->body = ['name' => 'Test'];
        $invoker = new FakeInvoker($ro);

        $request = new Request($invoker, $ro, Method::GET, []);

        $pendingRequests = new PendingRequests(new SyncAsync());
        $asyncRequest = new AsyncRequest($request, $pendingRequests);

        $result = $asyncRequest();

        $this->assertInstanceOf(FakeResourceObject::class, $result);
        $this->assertSame(['name' => 'Test'], $result->body);
    }

    public function testQuery(): void
    {
        $ro = new FakeResourceObject('app://self/user');
        $invoker = new FakeInvoker($ro);
        $query = ['id' => '123', 'name' => 'test'];
        $request = new Request($invoker, $ro, Method::GET, $query);

        $pendingRequests = new PendingRequests(new SyncAsync());
        $asyncRequest = new AsyncRequest($request, $pendingRequests);

        $this->assertSame($query, $asyncRequest->query);
    }

    public function testToStringTriggersExecution(): void
    {
        $ro = new FakeResourceObject('app://self/user');
        $ro->body = ['name' => 'Test'];
        $invoker = new FakeInvoker($ro);

        $request = new Request($invoker, $ro, Method::GET, []);

        $allRequests = new PendingRequests(new SyncAsync());
        $asyncRequest = new AsyncRequest($request, $allRequests);

        // When __toString is called, execution should happen
        $result = (string) $asyncRequest;

        $this->assertNotEmpty($result);
    }

    public function testWithQueryRekeysPendingRequest(): void
    {
        $ro = new FakeResourceObject('app://self/user');
        $invoker = new FakeInvoker($ro);
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
        $ro = new FakeResourceObject('app://self/user');
        $invoker = new FakeInvoker($ro);
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
        $ro = new FakeResourceObject('app://self/article');
        $ro->body = ['title' => 'hello'];
        $invoker = new FakeInvoker($ro, false);

        $request = new Request($invoker, $ro, Method::GET, []);

        // Adapters treat keys as opaque and must echo them back; the fake
        // reflects that by deriving the rendered view from the input key
        // (a request hash), not the URI.
        $async = new class implements AsyncInterface {
            /** {@inheritDoc} */
            public function __invoke(array $tasks): void
            {
            }

            /** {@inheritDoc} */
            public function execute(array $requests): array
            {
                $results = [];
                foreach ($requests as $key => $request) {
                    unset($request);
                    $results[$key] = '<rendered:' . $key . '>';
                }

                return $results;
            }
        };

        $pendingRequests = new PendingRequests($async);
        $asyncRequest = new AsyncRequest($request, $pendingRequests);

        $this->assertSame('<rendered:' . $asyncRequest->hash() . '>', (string) $asyncRequest);
    }

    public function testJsonSerializeUsesPendingBatch(): void
    {
        // Uses DeferredRequest (as AsyncEmbedInterceptor does in production)
        // so hash() discriminates by URI; a plain Request's inherited
        // hash() does not include the URI, only resourceObject::class.
        $provider = new FakePendingResourceProvider(new FakeResource());

        $request1 = new DeferredRequest($provider, Method::GET, 'app://self/one');
        $request2 = new DeferredRequest($provider, Method::GET, 'app://self/two');

        $async = new class implements AsyncInterface {
            public int $executeCount = 0;

            /** {@inheritDoc} */
            public function __invoke(array $tasks): void
            {
            }

            /** {@inheritDoc} */
            public function execute(array $requests): array
            {
                $this->executeCount++;

                $results = [];
                foreach ($requests as $key => $request) {
                    $results[$key] = $request->toUri() === 'app://self/one'
                        ? '{"name":"one"}'
                        : '{"name":"two"}';
                }

                return $results;
            }
        };

        $pendingRequests = new PendingRequests($async);
        $asyncRequest1 = new AsyncRequest($request1, $pendingRequests);
        $asyncRequest2 = new AsyncRequest($request2, $pendingRequests);

        $json = json_encode(['one' => $asyncRequest1, 'two' => $asyncRequest2], JSON_THROW_ON_ERROR);

        $this->assertSame('{"one":{"name":"one"},"two":{"name":"two"}}', $json);
        $this->assertSame(1, $async->executeCount);
    }

    public function testToStringAfterJsonSerializeUsesCachedRenderedView(): void
    {
        $invoker = new FakeInvoker(new FakeResourceObject('app://self/unused'), false);
        $request = new Request($invoker, new FakeResourceObject('app://self/article'), Method::GET, []);

        $async = new class implements AsyncInterface {
            public int $executeCount = 0;

            /** {@inheritDoc} */
            public function __invoke(array $tasks): void
            {
            }

            /** {@inheritDoc} */
            public function execute(array $requests): array
            {
                $this->executeCount++;

                $results = [];
                foreach ($requests as $key => $request) {
                    unset($request);
                    $results[$key] = '{"title":"hello"}';
                }

                return $results;
            }
        };

        $pendingRequests = new PendingRequests($async);
        $asyncRequest = new AsyncRequest($request, $pendingRequests);

        $this->assertSame('{"title":"hello"}', json_encode($asyncRequest, JSON_THROW_ON_ERROR));
        $this->assertSame('{"title":"hello"}', (string) $asyncRequest);
        $this->assertSame(1, $async->executeCount);
    }

    public function testHashDelegatesToInnerRequest(): void
    {
        $ro = new FakeResourceObject('app://self/user');
        $invoker = new FakeInvoker($ro);
        $request = new Request($invoker, $ro, Method::GET, []);

        $pendingRequests = new PendingRequests(new SyncAsync());
        $asyncRequest = new AsyncRequest($request, $pendingRequests);

        $this->assertSame($request->hash(), $asyncRequest->hash());
    }

    public function testHashDiffersForSameUriWithDifferentLinks(): void
    {
        // F6: hash() must be identity-sensitive to links, not just URI, so
        // that a plain request and a ->linkCrawl() request to the same URI
        // are not conflated by PendingRequests.
        $resource = new FakeResource();
        $provider = new FakePendingResourceProvider($resource);

        $plain = new DeferredRequest($provider, Method::GET, 'app://self/user');
        $crawled = new DeferredRequest($provider, Method::GET, 'app://self/user');
        $crawled->linkCrawl('tree');

        $pendingRequests = new PendingRequests(new SyncAsync());
        $asyncPlain = new AsyncRequest($plain, $pendingRequests);
        $asyncCrawled = new AsyncRequest($crawled, $pendingRequests);

        $this->assertSame($asyncPlain->toUri(), $asyncCrawled->toUri());
        $this->assertNotSame($asyncPlain->hash(), $asyncCrawled->hash());
    }

    public function testLinkCrawlRekeysPendingRequest(): void
    {
        $resource = new FakeResource();
        $provider = new FakePendingResourceProvider($resource);
        $request = new DeferredRequest($provider, Method::GET, 'app://self/user');

        $pendingRequests = new PendingRequests(new SyncAsync());
        $asyncRequest = new AsyncRequest($request, $pendingRequests);
        $originalHash = $asyncRequest->hash();

        $asyncRequest->linkCrawl('tree');

        $this->assertNotSame($originalHash, $asyncRequest->hash());
        // Resolves under the new hash without ResultNotFoundException.
        $this->assertNotEmpty((string) $asyncRequest);
    }

    public function testLinkSelfRekeysPendingRequest(): void
    {
        $resource = new FakeResource();
        $provider = new FakePendingResourceProvider($resource);
        $request = new DeferredRequest($provider, Method::GET, 'app://self/user');

        $pendingRequests = new PendingRequests(new SyncAsync());
        $asyncRequest = new AsyncRequest($request, $pendingRequests);
        $originalHash = $asyncRequest->hash();

        $asyncRequest->linkSelf('profile');

        $this->assertNotSame($originalHash, $asyncRequest->hash());
        $this->assertNotEmpty((string) $asyncRequest);
    }

    public function testLinkNewRekeysPendingRequest(): void
    {
        $resource = new FakeResource();
        $provider = new FakePendingResourceProvider($resource);
        $request = new DeferredRequest($provider, Method::GET, 'app://self/user');

        $pendingRequests = new PendingRequests(new SyncAsync());
        $asyncRequest = new AsyncRequest($request, $pendingRequests);
        $originalHash = $asyncRequest->hash();

        $asyncRequest->linkNew('profile');

        $this->assertNotSame($originalHash, $asyncRequest->hash());
        $this->assertNotEmpty((string) $asyncRequest);
    }
}
