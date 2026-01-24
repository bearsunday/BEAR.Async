<?php

declare(strict_types=1);

namespace BEAR\Async\Demo\Resource\App\Slow;

use BEAR\Resource\ResourceObject;

use function asyncDelay;

class Settings extends ResourceObject
{
    public function onGet(): static
    {
        asyncDelay(5);
        $this->body = ['settings' => ['theme' => 'dark', 'lang' => 'en']];

        return $this;
    }
}
