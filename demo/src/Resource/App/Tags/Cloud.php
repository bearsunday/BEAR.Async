<?php

declare(strict_types=1);

namespace BEAR\AsyncDemo\Resource\App\Tags;

use BEAR\AsyncDemo\Annotation\SlowQuery;
use BEAR\AsyncDemo\Query\TagQueryInterface;
use BEAR\Resource\ResourceObject;

use function array_map;

/**
 * Tags cloud resource - tag names with usage counts
 */
class Cloud extends ResourceObject
{
    public function __construct(
        private readonly TagQueryInterface $tagQuery,
    ) {
    }

    #[SlowQuery]
    public function onGet(): static
    {
        $tags = $this->tagQuery->cloud();
        $this->body = array_map(static fn ($t) => (array) $t, $tags);

        return $this;
    }
}
