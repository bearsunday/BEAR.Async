<?php

declare(strict_types=1);

namespace BEAR\AsyncDemo\Query;

use BEAR\AsyncDemo\Entity\Activity;
use Ray\MediaQuery\Annotation\DbQuery;

interface ActivityQueryInterface
{
    /**
     * @return list<Activity>
     */
    #[DbQuery('activity_list_by_user')]
    public function listByUser(int $user_id, int $limit = 10): array;
}
