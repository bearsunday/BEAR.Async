<?php

declare(strict_types=1);

namespace BEAR\AsyncDemo\Resource\App\User;

use BEAR\AsyncDemo\Annotation\SlowQuery;
use BEAR\AsyncDemo\Query\NotificationQueryInterface;
use BEAR\Resource\ResourceObject;

/**
 * User notifications resource
 */
class Notifications extends ResourceObject
{
    public function __construct(
        private readonly NotificationQueryInterface $notificationQuery,
    ) {
    }

    #[SlowQuery]
    public function onGet(int $user_id): static
    {
        $notifications = $this->notificationQuery->listByUser($user_id);
        $this->body = array_map(static fn($n) => (array) $n, $notifications);

        return $this;
    }
}
