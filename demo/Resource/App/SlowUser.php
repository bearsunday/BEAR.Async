<?php

declare(strict_types=1);

namespace BEAR\Async\Demo\Resource\App;

use BEAR\Resource\Annotation\Link;
use BEAR\Resource\ResourceObject;

use function asyncDelay;

/**
 * Slow user resource with 200ms delay
 */
class SlowUser extends ResourceObject
{
    #[Link(rel: 'posts', href: 'app://self/slow-posts?user_id={id}', crawl: 'tree')]
    public function onGet(): static
    {
        asyncDelay(200); // 200ms delay
        $this->body = [
            ['id' => 1, 'name' => 'User 1'],
            ['id' => 2, 'name' => 'User 2'],
        ];

        return $this;
    }
}
