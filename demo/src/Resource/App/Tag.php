<?php

declare(strict_types=1);

namespace BEAR\AsyncDemo\Resource\App;

use BEAR\AsyncDemo\Query\TagQueryInterface;
use BEAR\Resource\ResourceObject;

/**
 * Tag resource - crawl leaf
 */
class Tag extends ResourceObject
{
    public function __construct(
        private readonly TagQueryInterface $tagQuery,
    ) {
    }

    public function onGet(int $post_id): static
    {
        $tags = $this->tagQuery->listByPost($post_id);
        $this->body = array_map(static fn($t) => (array) $t, $tags);

        return $this;
    }
}
