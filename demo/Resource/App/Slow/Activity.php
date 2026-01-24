<?php

declare(strict_types=1);

namespace BEAR\Async\Demo\Resource\App\Slow;

use BEAR\Resource\ResourceObject;

use function asyncDelay;

class Activity extends ResourceObject
{
    public function onGet(): static
    {
        asyncDelay(5);
        $this->body = ['activity' => [['action' => 'login', 'time' => '2024-01-01']]];

        return $this;
    }
}
