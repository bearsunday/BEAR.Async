<?php

declare(strict_types=1);

namespace BEAR\AsyncDemo\Resource\App;

use BEAR\AsyncDemo\Annotation\SlowQuery;
use BEAR\AsyncDemo\Query\CategoryQueryInterface;
use BEAR\Resource\ResourceObject;

/**
 * Categories resource
 */
class Categories extends ResourceObject
{
    public function __construct(
        private readonly CategoryQueryInterface $categoryQuery,
    ) {
    }

    #[SlowQuery]
    public function onGet(): static
    {
        $categories = $this->categoryQuery->list();
        $this->body = array_map(static fn($c) => (array) $c, $categories);

        return $this;
    }
}
