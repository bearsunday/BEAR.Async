<?php

declare(strict_types=1);

namespace BEAR\AsyncDemo\Query;

use BEAR\AsyncDemo\Entity\Comment;
use Ray\MediaQuery\Annotation\DbQuery;

interface CommentQueryInterface
{
    /** @return list<Comment> */
    #[DbQuery('comment_list_by_post')]
    public function listByPost(int $post_id): array;
}
