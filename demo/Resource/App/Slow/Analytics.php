<?php

declare(strict_types=1);

namespace BEAR\Async\Demo\Resource\App\Slow;

use BEAR\Resource\ResourceObject;

use function asyncDelay;

class Analytics extends ResourceObject
{
    public function onGet(): static
    {
        asyncDelay(5);
        $this->body = ['analytics' => ['views' => 1000, 'clicks' => 50]];

        return $this;
    }
}
