<?php

declare(strict_types=1);

namespace BEAR\AsyncDemo\Resource\App;

use BEAR\AsyncDemo\Query\AuthorQueryInterface;
use BEAR\Resource\Annotation\Link;
use BEAR\Resource\ResourceObject;

use function array_map;

/**
 * Author resource - crawl root
 *
 * Links to posts for hierarchical crawl demonstration
 */
class Author extends ResourceObject
{
    public function __construct(
        private readonly AuthorQueryInterface $authorQuery,
    ) {
    }

    #[Link(rel: 'posts', href: 'app://self/post?author_id={id}', crawl: 'tree')]
    public function onGet(): static
    {
        $authors = $this->authorQuery->list();
        $this->body = array_map(static fn ($a) => (array) $a, $authors);

        return $this;
    }
}
