<?php

declare(strict_types=1);

namespace BEAR\AsyncDemo\Query;

use BEAR\AsyncDemo\Entity\Post;
use BEAR\AsyncDemo\Entity\PostWithAuthor;
use Ray\MediaQuery\Annotation\DbQuery;

interface PostQueryInterface
{
    /**
     * @return list<Post>
     */
    #[DbQuery('post_list_by_author')]
    public function listByAuthor(int $author_id): array;

    /**
     * @return list<PostWithAuthor>
     */
    #[DbQuery('post_list_recent')]
    public function listRecent(int $limit = 5): array;

    /**
     * @return list<PostWithAuthor>
     */
    #[DbQuery('post_list_popular')]
    public function listPopular(int $limit = 5): array;
}
