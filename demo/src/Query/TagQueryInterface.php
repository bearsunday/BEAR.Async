<?php

declare(strict_types=1);

namespace BEAR\AsyncDemo\Query;

use BEAR\AsyncDemo\Entity\Tag;
use BEAR\AsyncDemo\Entity\TagWithCount;
use Ray\MediaQuery\Annotation\DbQuery;

interface TagQueryInterface
{
    /**
     * @return list<Tag>
     */
    #[DbQuery('tag_list_by_post')]
    public function listByPost(int $post_id): array;

    /**
     * @return list<TagWithCount>
     */
    #[DbQuery('tag_cloud')]
    public function cloud(): array;
}
