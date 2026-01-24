<?php

declare(strict_types=1);

namespace BEAR\Async\Demo\Resource\App\Slow;

use BEAR\Resource\ResourceObject;

use function asyncDelay;

class Comments extends ResourceObject
{
    public function onGet(): static
    {
        asyncDelay(5);
        $this->body = ['comments' => [['id' => 1, 'body' => 'Comment 1']]];

        return $this;
    }
}
