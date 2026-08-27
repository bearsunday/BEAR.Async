<?php

declare(strict_types=1);

namespace BEAR\Async;

use BEAR\Async\Adapter\SyncAsync;
use BEAR\Async\Exception\ResultNotFoundException;
use BEAR\Async\Fake\FakeInvoker;
use BEAR\Async\Fake\FakePendingResourceProvider;
use BEAR\Async\Fake\FakeResource;
use BEAR\Async\Fake\FakeResourceObject;
use BEAR\Async\Fake\FakeSwooleThrowingInvoker;
use BEAR\Resource\Method;
use BEAR\Resource\Request;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class PendingRequestsTest extends TestCase
{
    public function testAddAndGetResult(): void
    {
        $ro = new FakeResourceObject('app://self/user');
        $ro->body = ['name' => 'Test'];
        $invoker = new FakeInvoker($ro);

        $request = new Request($invoker, $ro, Method::GET, []);

        $allRequests = new PendingRequests(new SyncAsync());
        $asyncRequest = new AsyncRequest($request, $allRequests);

        // When we call __toString on AsyncRequest, it should get the result
        $result = (string) $asyncRequest;

        $this->assertNotEmpty($result);
    }

    public function testDeduplicatesRequests(): void
    {
        $ro = new FakeResourceObject('app://self/user');
        $ro->body = ['name' => 'Test'];
        $invoker = new FakeInvoker($ro);

        $request1 = new Request($invoker, $ro, Method::GET, []);
        $request2 = new Request($invoker, $ro, Method::GET, []);

        $allRequests = new PendingRequests(new SyncAsync());

        // Both requests have the same URI, method and links (same hash)
        $asyncRequest1 = new AsyncRequest($request1, $allRequests);
        $asyncRequest2 = new AsyncRequest($request2, $allRequests);

        $this->assertSame($asyncRequest1->hash(), $asyncRequest2->hash());

        // Should return the same result for the same hash
        $result1 = (string) $asyncRequest1;
        $result2 = (string) $asyncRequest2;

        $this->assertSame($result1, $result2);
        $this->assertSame(1, $invoker->invokeCount);
    }

    public function testSameUriDifferentLinksAreNotDeduplicated(): void
    {
        // F6: URI-only keys silently dropped link variants. Two requests to
        // the same URI, one plain and one with ->linkCrawl(), must be kept
        // as two distinct pending entries and both must execute.
        $resource = new FakeResource();
        $provider = new FakePendingResourceProvider($resource);

        $plain = new DeferredRequest($provider, Method::GET, 'app://self/user');
        $crawled = new DeferredRequest($provider, Method::GET, 'app://self/user');
        $crawled->linkCrawl('tree');

        $this->assertNotSame($plain->hash(), $crawled->hash());
        $this->assertSame($plain->toUri(), $crawled->toUri());

        $pendingRequests = new PendingRequests(new SyncAsync());
        $asyncPlain = new AsyncRequest($plain, $pendingRequests);
        $asyncCrawled = new AsyncRequest($crawled, $pendingRequests);

        $this->assertNotSame($asyncPlain->hash(), $asyncCrawled->hash());

        // Both must resolve without throwing ResultNotFoundException.
        $this->assertNotEmpty((string) $asyncPlain);
        $this->assertNotEmpty((string) $asyncCrawled);
    }

    public function testResetClearsCache(): void
    {
        $ro = new FakeResourceObject('app://self/user');
        $ro->body = ['name' => 'Test'];
        $invoker = new FakeInvoker($ro);

        $request = new Request($invoker, $ro, Method::GET, []);

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
        $ro = new FakeResourceObject('app://self/user');
        $ro->body = ['name' => 'Test'];
        $invoker = new FakeInvoker($ro);

        $request = new Request($invoker, $ro, Method::GET, []);

        $allRequests = new PendingRequests(new SyncAsync());
        $asyncRequest = new AsyncRequest($request, $allRequests);

        // Get result twice - invoke should only happen once
        $result1 = (string) $asyncRequest;
        $result2 = (string) $asyncRequest;

        $this->assertSame($result1, $result2);
        $this->assertSame(1, $invoker->invokeCount);
    }

    public function testDifferentQueriesAreNotDeduplicated(): void
    {
        $ro = new FakeResourceObject('app://self/user');
        $ro->body = ['name' => 'Test'];
        $invoker = new FakeInvoker($ro);

        $request1 = new Request($invoker, $ro, Method::GET, ['id' => '1']);
        $request2 = new Request($invoker, $ro, Method::GET, ['id' => '2']);

        $pendingRequests = new PendingRequests(new SyncAsync());
        $asyncRequest1 = new AsyncRequest($request1, $pendingRequests);
        $asyncRequest2 = new AsyncRequest($request2, $pendingRequests);

        // Different URIs (due to different query params) should both execute
        (string) $asyncRequest1;
        (string) $asyncRequest2;

        // Verify URIs are different
        $this->assertNotSame($asyncRequest1->toUri(), $asyncRequest2->toUri());
        $this->assertSame(2, $invoker->invokeCount);
    }

    public function testWithQueryChangesKeyOldKeyGoneNewKeyPending(): void
    {
        $ro = new FakeResourceObject('app://self/user');
        $ro->body = ['name' => 'Test'];
        $invoker = new FakeInvoker($ro);
        $request = new Request($invoker, $ro, Method::GET, []);

        $pendingRequests = new PendingRequests(new SyncAsync());
        $asyncRequest = new AsyncRequest($request, $pendingRequests);
        $originalKey = $asyncRequest->hash();

        $asyncRequest->withQuery(['id' => '7']);
        $newKey = $asyncRequest->hash();

        $this->assertNotSame($originalKey, $newKey);

        // __toString resolves under the new key without throwing.
        $this->assertNotEmpty((string) $asyncRequest);
    }

    public function testLinkCrawlAfterConstructionRekeys(): void
    {
        $resource = new FakeResource();
        $provider = new FakePendingResourceProvider($resource);

        $request = new DeferredRequest($provider, Method::GET, 'app://self/user');

        $pendingRequests = new PendingRequests(new SyncAsync());
        $asyncRequest = new AsyncRequest($request, $pendingRequests);
        $originalKey = $asyncRequest->hash();

        $asyncRequest->linkCrawl('tree');
        $newKey = $asyncRequest->hash();

        $this->assertNotSame($originalKey, $newKey);

        // Resolves under the new (rekeyed) hash without throwing.
        $this->assertNotEmpty((string) $asyncRequest);
    }

    public function testDirectInvokeThenToStringExecutesOnce(): void
    {
        $ro = new FakeResourceObject('app://self/user');
        $ro->body = ['name' => 'Test'];
        $invoker = new FakeInvoker($ro);
        $request = new Request($invoker, $ro, Method::GET, []);

        $pendingRequests = new PendingRequests(new SyncAsync());
        $asyncRequest = new AsyncRequest($request, $pendingRequests);

        // Direct invocation (also the path behind __get/offsetGet) must
        // remove the request from the batch; rendering later reuses the
        // already-invoked ResourceObject instead of executing again.
        $result = $asyncRequest();

        $this->assertSame(['name' => 'Test'], $result->body);
        $this->assertNotEmpty((string) $asyncRequest);
        $this->assertSame(1, $invoker->invokeCount);
    }

    public function testDirectInvokeWithQueryRekeysAndResolves(): void
    {
        $ro = new FakeResourceObject('app://self/user');
        $ro->body = ['name' => 'Test'];
        $invoker = new FakeInvoker($ro);
        $request = new Request($invoker, $ro, Method::GET, []);

        $pendingRequests = new PendingRequests(new SyncAsync());
        $asyncRequest = new AsyncRequest($request, $pendingRequests);
        $originalKey = $asyncRequest->hash();

        $asyncRequest(['id' => '9']);

        $this->assertNotSame($originalKey, $asyncRequest->hash());
        $this->assertStringContainsString('id=9', $asyncRequest->toUri());
        $this->assertNotEmpty((string) $asyncRequest);
        $this->assertSame(1, $invoker->invokeCount);
    }

    public function testFailedBatchIsAbandonedNotReExecuted(): void
    {
        $ro = new FakeResourceObject('app://self/ok');
        $ro->body = ['name' => 'ok'];
        $okInvoker = new FakeInvoker($ro);
        $okRequest = new Request($okInvoker, $ro, Method::GET, []);

        $failure = new RuntimeException('embed failed');
        $failInvoker = new FakeSwooleThrowingInvoker($failure);
        $failRequest = new Request($failInvoker, new FakeResourceObject('app://self/fail'), Method::GET, []);

        $pendingRequests = new PendingRequests(new SyncAsync());
        $okAsync = new AsyncRequest($okRequest, $pendingRequests);
        $failAsync = new AsyncRequest($failRequest, $pendingRequests);

        try {
            (string) $okAsync;
            $this->fail('The batch failure did not propagate');
        } catch (RuntimeException $e) {
            $this->assertSame($failure, $e);
        }

        // The request that completed before the batch failed still resolves,
        // without executing again.
        $this->assertNotEmpty((string) $okAsync);

        // The failed request misses — and, critically, the batch (with its
        // side effects) is not replayed to look for it.
        try {
            (string) $failAsync;
            $this->fail('ResultNotFoundException was not thrown');
        } catch (ResultNotFoundException) {
        }

        $this->assertSame(1, $okInvoker->invokeCount);
        $this->assertSame(1, $failInvoker->invokeCount);
    }

    public function testGetResultMissThrowsResultNotFoundExceptionWithUri(): void
    {
        $ro = new FakeResourceObject('app://self/unresolvable');
        $invoker = new FakeInvoker($ro, false);
        $request = new Request($invoker, $ro, Method::GET, []);

        // AsyncInterface whose execute() never returns anything for this
        // request, so getResult() must miss and throw with the URI visible.
        $async = new class implements AsyncInterface {
            /** {@inheritDoc} */
            public function __invoke(array $tasks): void
            {
            }

            /** {@inheritDoc} */
            public function execute(array $requests): array
            {
                unset($requests);

                return [];
            }
        };

        $pendingRequests = new PendingRequests($async);
        $asyncRequest = new AsyncRequest($request, $pendingRequests);

        $this->expectException(ResultNotFoundException::class);
        $this->expectExceptionMessage('app://self/unresolvable');

        (string) $asyncRequest;
    }
}
