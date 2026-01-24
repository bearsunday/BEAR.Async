<?php

declare(strict_types=1);

namespace BEAR\Async\Demo\Module;

use BEAR\Resource\Module\EmbedResourceModule;
use BEAR\Resource\Module\HalModule;
use BEAR\Resource\Module\ResourceModule;
use Ray\Di\AbstractModule;

/**
 * Simple module for sleep benchmark (no database dependencies)
 */
class SlowDemoModule extends AbstractModule
{
    protected function configure(): void
    {
        $this->install(new ResourceModule('BEAR\Async\Demo'));
        $this->install(new HalModule());
        $this->install(new EmbedResourceModule());
    }
}
