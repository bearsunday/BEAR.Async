<?php

declare(strict_types=1);

namespace BEAR\AsyncDemo\Query;

use BEAR\AsyncDemo\Entity\Author;
use Ray\MediaQuery\Annotation\DbQuery;

interface AuthorQueryInterface
{
    /** @return list<Author> */
    #[DbQuery('author_list')]
    public function list(): array;
}
