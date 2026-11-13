<?php

declare(strict_types=1);

namespace BEAR\Async;

use BEAR\Async\Adapter\SyncAsync;
use BEAR\Async\Fake\FakePendingResourceProvider;
use BEAR\Async\Fake\LinkProbe\Resource\App\Blog;
use BEAR\Resource\Exception\UriException;
use BEAR\Resource\Method;
use BEAR\Resource\Module\ResourceModule;
use BEAR\Resource\Request;
use BEAR\Resource\ResourceInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Ray\Di\Injector;

use function assert;
use function is_dir;
use function json_decode;
use function rmdir;
use function sys_get_temp_dir;
use function uniqid;

use const JSON_THROW_ON_ERROR;

/**
 * A DeferredRequest built from a URI that already carries a `#link=...`
 * fragment (reachable via `#[Embed(src: '...#link=...')]`) must behave like
 * one built plainly and then given the same linkSelf()/linkNew()/
 * linkCrawl() calls: toUri(), hash() and __invoke() all have to agree on
 * which links it carries.
 */
final class DeferredRequestLinkFragmentTest extends TestCase
{
    private ResourceInterface $resource;
    private FakePendingResourceProvider $provider;
    private string $tmpDir;

    protected function setUp(): void
    {
        Blog::$calls = 0;
        $this->tmpDir = sys_get_temp_dir() . '/bear_async_link_fragment_' . uniqid();
        $this->resource = (new Injector(new ResourceModule('BEAR\Async\Fake\LinkProbe'), $this->tmpDir))
            ->getInstance(ResourceInterface::class);
        $this->provider = new FakePendingResourceProvider($this->resource);
    }

    protected function tearDown(): void
    {
        if (is_dir($this->tmpDir)) {
            rmdir($this->tmpDir);
        }
    }

    public function testToUriAndInvokeMatchARealRequestBuiltFromTheSameFragment(): void
    {
        $uri = 'app://self/author?id=1#link=self:blog';
        $real = $this->resource->newRequest(Method::GET, $uri);
        assert($real instanceof Request);
        $deferred = new DeferredRequest($this->provider, Method::GET, $uri);

        $this->assertSame($real->toUri(), $deferred->toUri());
        $this->assertEquals($real->links, $deferred->links);

        $ro = $deferred();

        $this->assertSame(1, Blog::$calls, 'the self-linked resource must be fetched exactly once');
        $this->assertSame('Athos blog', $ro->body['name']);
    }

    /** @return array<string, array{0: string}> */
    public static function validFragments(): array
    {
        return [
            'no fragment' => [''],
            'bare hash' => ['#'],
            'self link' => ['#link=self:blog'],
            'new and crawl links' => ['#link=new:a,crawl:b-c_d'],
        ];
    }

    /**
     * For every fragment newRequest() accepts, DeferredRequest must accept
     * it too and hold the same links, in the same order.
     */
    #[DataProvider('validFragments')]
    public function testHoldsTheSameLinksNewRequestDoesForAValidFragment(string $fragment): void
    {
        $uri = 'app://self/author?id=1' . $fragment;
        $real = $this->resource->newRequest(Method::GET, $uri);
        assert($real instanceof Request);

        $deferred = new DeferredRequest($this->provider, Method::GET, $uri);

        $this->assertEquals($real->links, $deferred->links);
    }

    /** @return array<string, array{0: string}> */
    public static function invalidFragments(): array
    {
        return [
            'link= with nothing after' => ['#link='],
            'empty rel' => ['#link=self:'],
            'trailing comma' => ['#link=self:blog,'],
            'unknown link type' => ['#link=next:x'],
            'rel with invalid characters' => ['#link=self:a%20b'],
            'non-link fragment' => ['#title'],
            'key=value fragment' => ['#key=x'],
        ];
    }

    /**
     * For every fragment newRequest() rejects, DeferredRequest must reject
     * it too, the same way (UriException).
     */
    #[DataProvider('invalidFragments')]
    public function testRejectsTheSameFragmentNewRequestRejects(string $fragment): void
    {
        $uri = 'app://self/author?id=1' . $fragment;

        try {
            $this->resource->newRequest(Method::GET, $uri);
            $this->fail("newRequest() was expected to throw UriException for {$uri}");
        } catch (UriException) {
            // sanity check that the fixture's grammar assumption holds
        }

        $this->expectException(UriException::class);
        new DeferredRequest($this->provider, Method::GET, $uri);
    }

    /**
     * Before DeferredRequest parsed a URI's `#link=...` fragment into
     * $this->links, a request built from that fragment hashed identically
     * to the plain request for the same URI (toUri() silently dropped the
     * fragment, and $this->links stayed empty). PendingRequests dedups by
     * hash, so sharing a batch meant one of the two never actually ran and
     * both resolved to whichever did.
     */
    public function testFragmentLinkedAndPlainRequestsDoNotCollideInOneBatch(): void
    {
        $pending = new PendingRequests(new SyncAsync());
        $plain = new AsyncRequest(
            new DeferredRequest($this->provider, Method::GET, 'app://self/author', ['id' => 1]),
            $pending,
        );
        $linked = new AsyncRequest(
            new DeferredRequest($this->provider, Method::GET, 'app://self/author?id=1#link=self:blog'),
            $pending,
        );

        $plainJson = (string) $plain;
        $linkedJson = (string) $linked;

        $this->assertSame(1, Blog::$calls, 'the self-linked resource must be fetched exactly once');
        $this->assertNotSame($plainJson, $linkedJson);

        /** @var array<string, mixed> $plainBody */
        $plainBody = json_decode($plainJson, true, 512, JSON_THROW_ON_ERROR);
        /** @var array<string, mixed> $linkedBody */
        $linkedBody = json_decode($linkedJson, true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame('Athos', $plainBody['name'], 'the plain request must not pick up the linked body');
        $this->assertSame('Athos blog', $linkedBody['name']);
    }
}
