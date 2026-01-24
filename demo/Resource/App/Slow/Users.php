<?php

declare(strict_types=1);

namespace BEAR\Async\Demo\Resource\App\Slow;

use BEAR\Resource\ResourceObject;

use function asyncDelay;

class Users extends ResourceObject
{
    public function onGet(): static
    {
        asyncDelay(5);
        $this->body = ['users' => [['id' => 1, 'name' => 'Alice'], ['id' => 2, 'name' => 'Bob']]];

        return $this;
    }
}
