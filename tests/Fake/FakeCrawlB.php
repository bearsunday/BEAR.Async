<?php

declare(strict_types=1);

namespace BEAR\Async\Fake;

use BEAR\Resource\Annotation\Link;
use BEAR\Resource\ResourceObject;

final class FakeCrawlB extends ResourceObject
{
    #[Link(rel: 'd', href: 'app://self/d?id={id}', crawl: 'tree')]
    public function onGet(string $id): static
    {
        $this->body = ['id' => $id];

        return $this;
    }
}
