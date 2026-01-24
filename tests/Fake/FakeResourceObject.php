<?php

declare(strict_types=1);

namespace BEAR\Async\Fake;

use BEAR\Resource\ResourceObject;
use BEAR\Resource\Uri;

class FakeResourceObject extends ResourceObject
{
    /** @var array<string, mixed> */
    public $body = [];

    public function __construct(string $uri = 'app://self/test')
    {
        $this->uri = new Uri($uri);
    }

    public function onGet(): static
    {
        return $this;
    }
}
