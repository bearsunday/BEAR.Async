<?php

declare(strict_types=1);

namespace BEAR\Async\Fake\LinkProbe\Resource\App;

use BEAR\Resource\ResourceObject;

/** Counts onGet() calls so a test can prove a self link was followed exactly once. */
final class Blog extends ResourceObject
{
    public static int $calls = 0;

    public function onGet(int $id): static
    {
        self::$calls++;
        $this->body = ['id' => $id, 'name' => 'Athos blog'];

        return $this;
    }
}
