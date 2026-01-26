<?php

declare(strict_types=1);

namespace BEAR\AsyncDemo\Resource\App\Posts;

use BEAR\AsyncDemo\Annotation\SlowQuery;
use BEAR\AsyncDemo\Query\PostQueryInterface;
use BEAR\Resource\ResourceObject;

use function array_map;

/**
 * Popular posts resource
 */
class Popular extends ResourceObject
{
    public function __construct(
        private readonly PostQueryInterface $postQuery,
    ) {
    }

    #[SlowQuery]
    public function onGet(int $limit = 5): static
    {
        $posts = $this->postQuery->listPopular($limit);
        $this->body = array_map(static fn ($p) => (array) $p, $posts);

        return $this;
    }
}
