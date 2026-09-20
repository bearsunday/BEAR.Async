<?php

declare(strict_types=1);

namespace BEAR\Async;

use BEAR\Async\Adapter\SyncAsync;
use BEAR\Async\Fake\FakeCrawlB;
use BEAR\Async\Fake\FakeCrawlC;
use BEAR\Async\Fake\FakeCrawlD;
use BEAR\Async\Fake\FakeCrawlE;
use BEAR\Async\Fake\FakeCrawlFactory;
use BEAR\Async\Fake\FakeCrawlInvoker;
use BEAR\Resource\Annotation\Link;
use BEAR\Resource\LinkCrawler;
use BEAR\Resource\LinkType;
use PHPUnit\Framework\TestCase;

use function array_unique;

class AsyncLinkCrawlerTest extends TestCase
{
    private FakeCrawlInvoker $invoker;
    private AsyncLinkCrawler $crawler;

    protected function setUp(): void
    {
        $this->invoker = new FakeCrawlInvoker();
        $factory = new FakeCrawlFactory([
            '/b' => FakeCrawlB::class,
            '/c' => FakeCrawlC::class,
            '/d' => FakeCrawlD::class,
            '/e' => FakeCrawlE::class,
        ]);
        $this->crawler = new AsyncLinkCrawler(
            $this->invoker,
            $factory,
            new SyncAsync(),
            new LinkCrawler($this->invoker, $factory),
        );
    }

    /**
     * A --crawl--> B --crawl--> D --crawl--> E
     * A --crawl--> D
     *
     * The hit on D lands while D's own links are still pending. Every copy of D
     * must still end up with E resolved.
     */
    public function testDiamondReachResolvesNestedLinksOnEveryPath(): void
    {
        $annotations = [
            new Link(rel: 'b', href: 'app://self/b?id={id}', crawl: 'tree'),
            new Link(rel: 'd', href: 'app://self/d?id={id}', crawl: 'tree'),
        ];
        $link = new LinkType('tree', LinkType::CRAWL_LINK);
        $bodyList = [['id' => '1']];

        $this->crawler->crawl($annotations, $link, $bodyList);

        $body = $bodyList[0];
        $this->assertIsArray($body['d']);
        $this->assertIsArray($body['b']['d']);
        $viaDirect = $body['d'];
        $viaB = $body['b']['d'];
        $this->assertArrayHasKey('e', $viaDirect);
        $this->assertArrayHasKey('e', $viaB);
        $this->assertSame(['id' => '1'], $viaDirect['e']);
        $this->assertSame($viaDirect, $viaB);
        $this->assertSame(
            ['app://self/b?id=1', 'app://self/d?id=1', 'app://self/e?id=1'],
            array_unique($this->invoker->invoked),
        );
    }

    /**
     * A --crawl--> B --crawl--> D --crawl--> E
     * A --crawl--> C --crawl--> D
     *
     * C's hit on D lands after D was already fully crawled under B. The copy
     * under C must be the fully crawled D, not the shallow result from before
     * B's nested links were resolved.
     */
    public function testSiblingPathsBothSeeFullyCrawledResult(): void
    {
        $annotations = [
            new Link(rel: 'b', href: 'app://self/b?id={id}', crawl: 'tree'),
            new Link(rel: 'c', href: 'app://self/c?id={id}', crawl: 'tree'),
        ];
        $link = new LinkType('tree', LinkType::CRAWL_LINK);
        $bodyList = [['id' => '1']];

        $this->crawler->crawl($annotations, $link, $bodyList);

        $body = $bodyList[0];
        $this->assertIsArray($body['b']['d']);
        $this->assertIsArray($body['c']['d']);
        $viaB = $body['b']['d'];
        $viaC = $body['c']['d'];
        $this->assertArrayHasKey('e', $viaB);
        $this->assertArrayHasKey('e', $viaC);
        $this->assertSame($viaB, $viaC);
        $this->assertSame(
            ['app://self/b?id=1', 'app://self/c?id=1', 'app://self/d?id=1', 'app://self/e?id=1'],
            array_unique($this->invoker->invoked),
        );
        $this->assertCount(4, $this->invoker->invoked);
    }
}
