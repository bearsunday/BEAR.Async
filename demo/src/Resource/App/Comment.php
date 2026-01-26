<?php

declare(strict_types=1);

namespace BEAR\AsyncDemo\Resource\App;

use BEAR\AsyncDemo\Query\CommentQueryInterface;
use BEAR\Resource\ResourceObject;

use function array_map;

/**
 * Comment resource - crawl leaf
 */
class Comment extends ResourceObject
{
    public function __construct(
        private readonly CommentQueryInterface $commentQuery,
    ) {
    }

    public function onGet(int $post_id): static
    {
        $comments = $this->commentQuery->listByPost($post_id);
        $this->body = array_map(static fn ($c) => (array) $c, $comments);

        return $this;
    }
}
