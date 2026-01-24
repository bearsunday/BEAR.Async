<?php

declare(strict_types=1);

namespace BEAR\Async\Demo\Resource\App\Slow;

use BEAR\Resource\ResourceObject;

use function asyncDelay;

class Profile extends ResourceObject
{
    public function onGet(): static
    {
        asyncDelay(5);
        $this->body = ['profile' => ['name' => 'John', 'email' => 'john@example.com']];

        return $this;
    }
}
