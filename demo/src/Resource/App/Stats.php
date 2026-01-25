<?php

declare(strict_types=1);

namespace BEAR\AsyncDemo\Resource\App;

use BEAR\AsyncDemo\Annotation\SlowQuery;
use BEAR\AsyncDemo\Query\StatsQueryInterface;
use BEAR\Resource\ResourceObject;

/**
 * Stats resource - aggregate statistics
 */
class Stats extends ResourceObject
{
    public function __construct(
        private readonly StatsQueryInterface $statsQuery,
    ) {
    }

    #[SlowQuery]
    public function onGet(): static
    {
        $this->body = (array) $this->statsQuery->aggregate();

        return $this;
    }
}
