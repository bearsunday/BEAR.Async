<?php

declare(strict_types=1);

namespace BEAR\Async;

use BEAR\Async\Adapter\SyncAsync;
use BEAR\Async\Fake\FakePendingResourceProvider;
use BEAR\Async\Fake\FakeResource;
use BEAR\Async\Module\AsyncEmbedModule;
use BEAR\Resource\Exception\EmbedException;
use BEAR\Resource\Exception\LinkException;
use BEAR\Resource\Method;
use BEAR\Resource\Module\ResourceModule;
use BEAR\Resource\ResourceInterface;
use PHPUnit\Framework\TestCase;
use Ray\Di\AbstractModule;
use Ray\Di\Injector;

/** The Guarded resource throws LogicException from onPost, so a test fails with it if an embed runs the POST link */
final class SafeEmbedLinksTest extends TestCase
{
    public function testAnEmbedRefusesALinkThatIsNotGet(): void
    {
        $body = $this->resource()->get('app://self/embed-unsafe')->body;
        $item = is_array($body) ? $body['item'] ?? null : null;
        $this->assertInstanceOf(AsyncRequest::class, $item);

        $this->expectException(LinkException::class);

        $item();
    }

    public function testASelfEmbedRefusesALinkThatIsNotGet(): void
    {
        try {
            $this->resource()->get('app://self/embed-unsafe-self');
            $this->fail('EmbedException should be thrown.');
        } catch (EmbedException $e) {
            $this->assertInstanceOf(LinkException::class, $e->getPrevious());
        }
    }

    public function testAnAsyncRequestCarriesTheReadOnlyFlagOfTheRequestItWraps(): void
    {
        $inner = new DeferredRequest(new FakePendingResourceProvider(new FakeResource()), Method::GET, 'app://self/guarded');
        $inner->safeLinksOnly = true;

        $this->assertTrue((new AsyncRequest($inner, new PendingRequests(new SyncAsync())))->safeLinksOnly);
    }

    public function testAReadOnlyRequestIsNotTheSameRequestAsOnePlacedByHand(): void
    {
        $provider = new FakePendingResourceProvider(new FakeResource());
        $byHand = new DeferredRequest($provider, Method::GET, 'app://self/guarded#link=self:doTouch');
        $embedded = new DeferredRequest($provider, Method::GET, 'app://self/guarded#link=self:doTouch');
        $embedded->safeLinksOnly = true;
        $pending = new PendingRequests(new SyncAsync());

        $this->assertNotSame($byHand->hash(), $embedded->hash());
        $this->assertNotSame((new AsyncRequest($byHand, $pending))->hash(), (new AsyncRequest($embedded, $pending))->hash());
    }

    private function resource(): ResourceInterface
    {
        $module = new class extends AbstractModule {
            protected function configure(): void
            {
                $this->install(new AsyncEmbedModule());
                $this->install(new ResourceModule('BEAR\Async\Fake\SafeEmbed'));
            }
        };

        return (new Injector($module))->getInstance(ResourceInterface::class);
    }
}
