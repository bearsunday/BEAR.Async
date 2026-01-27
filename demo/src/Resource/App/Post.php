<?php

declare(strict_types=1);

namespace BEAR\AsyncDemo\Resource\App;

use BEAR\AsyncDemo\Query\PostQueryInterface;
use BEAR\Resource\Annotation\Link;
use BEAR\Resource\ResourceObject;

/**
 * Post resource - crawl level 2
 *
 * Links to comments and tags for parallel fetching
 */
class Post extends ResourceObject
{
    public function __construct(
        private readonly PostQueryInterface $postQuery,
    ) {
    }

    #[Link(rel: 'comments', href: 'app://self/comment?post_id={id}', crawl: 'tree')]
    #[Link(rel: 'tags', href: 'app://self/tag?post_id={id}', crawl: 'tree')]
    public function onGet(int $author_id): static
    {
        $posts = $this->postQuery->listByAuthor($author_id);
        $this->body = array_map(static fn($p) => (array) $p, $posts);

        return $this;
    }
}
