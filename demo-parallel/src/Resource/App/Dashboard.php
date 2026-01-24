<?php

declare(strict_types=1);

namespace BEAR\AsyncDemo\Resource\App;

use BEAR\Resource\Annotation\Embed;
use BEAR\Resource\ResourceObject;

class Dashboard extends ResourceObject
{
    #[Embed(rel: 'slow1', src: 'app://self/slow?id=1')]
    #[Embed(rel: 'slow2', src: 'app://self/slow?id=2')]
    #[Embed(rel: 'slow3', src: 'app://self/slow?id=3')]
    #[Embed(rel: 'slow4', src: 'app://self/slow?id=4')]
    #[Embed(rel: 'slow5', src: 'app://self/slow?id=5')]
    #[Embed(rel: 'slow6', src: 'app://self/slow?id=6')]
    #[Embed(rel: 'slow7', src: 'app://self/slow?id=7')]
    #[Embed(rel: 'slow8', src: 'app://self/slow?id=8')]
    #[Embed(rel: 'slow9', src: 'app://self/slow?id=9')]
    #[Embed(rel: 'slow10', src: 'app://self/slow?id=10')]
    public function onGet(): static
    {
        return $this;
    }
}
