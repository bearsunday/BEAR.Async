<?php

declare(strict_types=1);

namespace BEAR\AsyncDemo\Resource\App\User;

use BEAR\AsyncDemo\Annotation\SlowQuery;
use BEAR\AsyncDemo\Query\UserQueryInterface;
use BEAR\Resource\ResourceObject;

/**
 * User profile resource
 */
class Profile extends ResourceObject
{
    public function __construct(
        private readonly UserQueryInterface $userQuery,
    ) {
    }

    #[SlowQuery]
    public function onGet(int $id): static
    {
        $user = $this->userQuery->item($id);
        $this->body = $user !== null ? (array) $user : [];

        return $this;
    }
}
