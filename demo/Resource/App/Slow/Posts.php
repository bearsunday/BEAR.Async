<?php

declare(strict_types=1);

namespace BEAR\Async\Demo\Resource\App\Slow;

use BEAR\Resource\ResourceObject;

use function asyncDelay;

class Posts extends ResourceObject
{
    public function onGet(): static
    {
        asyncDelay(5);
        $this->body = ['posts' => [['id' => 1, 'title' => 'Post 1'], ['id' => 2, 'title' => 'Post 2']]];

        return $this;
    }
}
