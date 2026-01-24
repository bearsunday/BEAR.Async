<?php

declare(strict_types=1);

namespace BEAR\Async\Demo\Resource\App\Slow;

use BEAR\Resource\ResourceObject;

use function asyncDelay;

class Notifications extends ResourceObject
{
    public function onGet(): static
    {
        asyncDelay(5);
        $this->body = ['notifications' => [['id' => 1, 'message' => 'New message']]];

        return $this;
    }
}
