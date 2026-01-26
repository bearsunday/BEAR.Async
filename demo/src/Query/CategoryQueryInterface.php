<?php

declare(strict_types=1);

namespace BEAR\AsyncDemo\Query;

use BEAR\AsyncDemo\Entity\Category;
use Ray\MediaQuery\Annotation\DbQuery;

interface CategoryQueryInterface
{
    /** @return list<Category> */
    #[DbQuery('category_list')]
    public function list(): array;
}
