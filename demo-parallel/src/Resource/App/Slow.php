<?php

declare(strict_types=1);

namespace BEAR\AsyncDemo\Resource\App;

use BEAR\Resource\ResourceObject;

use function microtime;
use function usleep;

class Slow extends ResourceObject
{
    public function onGet(int $id = 0): static
    {
        usleep(5000); // 5ms
        $this->body = ['id' => $id, 'time' => microtime(true)];

        return $this;
    }
}
