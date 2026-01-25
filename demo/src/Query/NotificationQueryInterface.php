<?php

declare(strict_types=1);

namespace BEAR\AsyncDemo\Query;

use BEAR\AsyncDemo\Entity\Notification;
use Ray\MediaQuery\Annotation\DbQuery;

interface NotificationQueryInterface
{
    /**
     * @return list<Notification>
     */
    #[DbQuery('notification_list_by_user')]
    public function listByUser(int $user_id): array;
}
