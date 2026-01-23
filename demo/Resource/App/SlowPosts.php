<?php

declare(strict_types=1);

namespace BEAR\Async\Demo\Resource\App;

use BEAR\Resource\Annotation\Link;
use BEAR\Resource\ResourceObject;

use function asyncDelay;

/**
 * Slow posts resource with 200ms delay
 */
class SlowPosts extends ResourceObject
{
    #[Link(rel: 'comments', href: 'app://self/slow-comments?post_id={id}', crawl: 'tree')]
    public function onGet(int $user_id): static
    {
        asyncDelay(200); // 200ms delay
        $this->body = [
            ['id' => $user_id * 10, 'title' => "Post for user {$user_id}"],
            ['id' => $user_id * 10 + 1, 'title' => "Another post for user {$user_id}"],
        ];

        return $this;
    }
}
