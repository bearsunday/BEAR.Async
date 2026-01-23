<?php

declare(strict_types=1);

namespace BEAR\Async\Fake;

use BEAR\Resource\ResourceObject;

class FakeResourceObject extends ResourceObject
{
    /** @var array<string, mixed> */
    public $body = [];

    public function onGet(): static
    {
        return $this;
    }
}
