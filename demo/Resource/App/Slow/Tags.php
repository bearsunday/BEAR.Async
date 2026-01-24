<?php

declare(strict_types=1);

namespace BEAR\Async\Demo\Resource\App\Slow;

use BEAR\Resource\ResourceObject;

use function asyncDelay;

class Tags extends ResourceObject
{
    public function onGet(): static
    {
        asyncDelay(5);
        $this->body = ['tags' => [['id' => 1, 'name' => 'php'], ['id' => 2, 'name' => 'async']]];

        return $this;
    }
}
