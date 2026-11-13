<?php

declare(strict_types=1);

namespace BEAR\Async\Adapter;

use BEAR\Async\AsyncRequest;
use BEAR\Async\DeferredRequest;
use BEAR\Async\Fake\FakePendingResourceProvider;
use BEAR\Async\Fake\LinkProbe\Resource\App\Blog;
use BEAR\Async\PendingRequests;
use BEAR\Resource\Method;
use BEAR\Resource\Module\ResourceModule;
use BEAR\Resource\Request;
use BEAR\Resource\ResourceInterface;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\TestCase;
use Ray\Di\Injector;
use Swoole\Coroutine;

use function assert;
use function is_dir;
use function json_decode;
use function rmdir;
use function sys_get_temp_dir;
use function uniqid;

use const JSON_THROW_ON_ERROR;

/**
 * A request carrying a self link must reach the linked resource exactly
 * once, not twice, however it is dispatched.
 */
final class LinkedRequestFollowsOnceTest extends TestCase
{
    private ResourceInterface $resource;
    private string $tmpDir;

    protected function setUp(): void
    {
        Blog::$calls = 0;
        $this->tmpDir = sys_get_temp_dir() . '/bear_async_link_probe_' . uniqid();
        $this->resource = (new Injector(new ResourceModule('BEAR\Async\Fake\LinkProbe'), $this->tmpDir))
            ->getInstance(ResourceInterface::class);
    }

    protected function tearDown(): void
    {
        if (is_dir($this->tmpDir)) {
            rmdir($this->tmpDir);
        }
    }

    /**
     * Pins the contract ParallelAsync::execute()'s worker closure relies on:
     * a single newRequest() call built from AsyncRequest::toUri() — link
     * fragment included — must follow a self link exactly once. ext-parallel
     * threads cannot be driven from a test process, so this replicates the
     * worker closure's one-request, no-replay call directly against a real,
     * DI-wired ResourceInterface.
     */
    public function testParallelAsyncWorkerPatternFollowsSelfLinkOnce(): void
    {
        $linked = $this->resource->newRequest(Method::GET, 'app://self/author', ['id' => 1])->linkSelf('blog');
        assert($linked instanceof Request);
        $uri = $linked->toUri();
        $this->assertSame('app://self/author?id=1#link=self:blog', $uri);

        // Mirrors ParallelAsync's worker closure: one newRequest() call built
        // from the full URI (with its link fragment); nothing replays the
        // link afterward.
        $ro = $this->resource->newRequest(Method::GET, $uri, [])();

        $this->assertSame(1, Blog::$calls, 'the self-linked resource must be fetched exactly once');
        $this->assertSame('Athos blog', $ro->body['name']);
    }

    /**
     * Exercises a real, running adapter (SwooleAsync, inside a coroutine)
     * instead of hand-replicating the worker closure: a DeferredRequest with
     * a self link, wrapped in AsyncRequest/PendingRequests, flushed via
     * `(string)`, must reach the linked resource exactly once.
     */
    #[RequiresPhpExtension('swoole')]
    public function testSwooleAsyncFollowsSelfLinkOnce(): void
    {
        $provider = new FakePendingResourceProvider($this->resource);
        $deferred = new DeferredRequest($provider, Method::GET, 'app://self/author', ['id' => 1]);
        $deferred->linkSelf('blog');

        $json = null;
        Coroutine\run(function () use ($deferred, &$json): void {
            $pendingRequests = new PendingRequests(new SwooleAsync());
            $async = new AsyncRequest($deferred, $pendingRequests);
            $json = (string) $async;
        });

        $this->assertSame(1, Blog::$calls, 'the self-linked resource must be fetched exactly once');
        /** @var array<string, mixed> $body */
        $body = json_decode((string) $json, true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame('Athos blog', $body['name']);
    }
}
