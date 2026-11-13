<?php

declare(strict_types=1);

namespace BEAR\Async\Adapter;

use BEAR\Async\Fake\LinkProbe\Resource\App\Blog;
use BEAR\Resource\Method;
use BEAR\Resource\Module\ResourceModule;
use BEAR\Resource\Request;
use BEAR\Resource\ResourceInterface;
use PHPUnit\Framework\TestCase;
use Ray\Di\Injector;

use function assert;
use function sys_get_temp_dir;

/**
 * Pins the contract ParallelAsync::execute()'s worker closure relies on: a
 * single newRequest() call built from AsyncRequest::toUri() — link fragment
 * included — must follow a self link exactly once. ext-parallel threads
 * cannot be driven from a test process, so this replicates the worker
 * closure's one-request, no-replay call directly against a real,
 * DI-wired ResourceInterface.
 */
final class ParallelAsyncWorkerLinkTest extends TestCase
{
    private ResourceInterface $resource;

    protected function setUp(): void
    {
        Blog::$calls = 0;
        $this->resource = (new Injector(new ResourceModule('BEAR\Async\Fake\LinkProbe'), sys_get_temp_dir()))
            ->getInstance(ResourceInterface::class);
    }

    public function testSingleNewRequestFromLinkedUriFollowsSelfLinkOnce(): void
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
}
