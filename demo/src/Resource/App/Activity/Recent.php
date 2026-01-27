<?php

declare(strict_types=1);

namespace BEAR\AsyncDemo\Resource\App\Activity;

use BEAR\AsyncDemo\Annotation\SlowQuery;
use BEAR\AsyncDemo\Query\ActivityQueryInterface;
use BEAR\Resource\ResourceObject;

/**
 * Recent activity resource
 */
class Recent extends ResourceObject
{
    public function __construct(
        private readonly ActivityQueryInterface $activityQuery,
    ) {
    }

    #[SlowQuery]
    public function onGet(int $user_id, int $limit = 10): static
    {
        $activities = $this->activityQuery->listByUser($user_id, $limit);
        $this->body = array_map(static fn($a) => (array) $a, $activities);

        return $this;
    }
}
