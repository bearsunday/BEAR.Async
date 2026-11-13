<?php

declare(strict_types=1);

namespace BEAR\Async\Fake\LinkProbe\Resource\App;

use BEAR\Resource\Annotation\Link;
use BEAR\Resource\ResourceObject;

final class Author extends ResourceObject
{
    #[Link(rel: 'blog', href: 'app://self/blog?id={id}')]
    public function onGet(int $id): static
    {
        $this->body = ['id' => $id, 'name' => 'Athos'];

        return $this;
    }
}
