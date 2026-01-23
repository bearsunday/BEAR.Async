<?php

declare(strict_types=1);

namespace BEAR\Async\Demo\Resource\App;

use BEAR\Resource\ResourceObject;

use function asyncDelay;

/**
 * Slow comments resource with 200ms delay (leaf node)
 */
class SlowComments extends ResourceObject
{
    public function onGet(int $post_id): static
    {
        asyncDelay(200); // 200ms delay
        $this->body = [
            ['id' => $post_id * 100, 'text' => "Comment on post {$post_id}"],
        ];

        return $this;
    }
}
