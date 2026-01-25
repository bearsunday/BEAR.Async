<?php

declare(strict_types=1);

namespace BEAR\AsyncDemo\Query;

use BEAR\AsyncDemo\Entity\User;
use Ray\MediaQuery\Annotation\DbQuery;

interface UserQueryInterface
{
    #[DbQuery('user_item', type: 'row')]
    public function item(int $id): User|null;
}
