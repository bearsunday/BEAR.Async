<?php

declare(strict_types=1);

namespace BEAR\Async\Demo\Resource\App\Slow;

use BEAR\Resource\ResourceObject;

use function asyncDelay;

class Categories extends ResourceObject
{
    public function onGet(): static
    {
        asyncDelay(5);
        $this->body = ['categories' => [['id' => 1, 'name' => 'Tech']]];

        return $this;
    }
}
