<?php

declare(strict_types=1);

namespace BEAR\AsyncDemo\Query;

use BEAR\AsyncDemo\Entity\Stats;
use Ray\MediaQuery\Annotation\DbQuery;

interface StatsQueryInterface
{
    #[DbQuery('stats_aggregate', type: 'row')]
    public function aggregate(): Stats;
}
