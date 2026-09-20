<?php

declare(strict_types=1);

namespace BEAR\Async\Fake;

use BEAR\Resource\Annotation\Link;
use BEAR\Resource\ResourceObject;

final class FakeCrawlD extends ResourceObject
{
    #[Link(rel: 'e', href: 'app://self/e?id={id}', crawl: 'tree')]
    public function onGet(string $id): static
    {
        $this->body = ['id' => $id];

        return $this;
    }
}
