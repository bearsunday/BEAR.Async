<?php

declare(strict_types=1);

namespace BEAR\Async\Fake;

use BEAR\Resource\Annotation\Link;
use BEAR\Resource\ResourceObject;

final class FakeCrawlG extends ResourceObject
{
    #[Link(rel: 'f', href: 'app://self/f?id={id}', crawl: 'tree')]
    public function onGet(string $id): static
    {
        $this->body = ['id' => $id];

        return $this;
    }
}
