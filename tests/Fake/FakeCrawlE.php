<?php

declare(strict_types=1);

namespace BEAR\Async\Fake;

use BEAR\Resource\ResourceObject;

final class FakeCrawlE extends ResourceObject
{
    public function onGet(string $id): static
    {
        $this->body = ['id' => $id];

        return $this;
    }
}
