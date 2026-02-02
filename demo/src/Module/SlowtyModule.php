<?php

declare(strict_types=1);

namespace BEAR\AsyncDemo\Module;

use BEAR\AsyncDemo\Renderer\SlowtyTemplateEngine;
use BEAR\Resource\RenderInterface;
use Ray\Di\AbstractModule;

/**
 * Slowty Template Engine Module
 *
 * Binds SlowtyTemplateEngine as the renderer with configurable delay.
 */
final class SlowtyModule extends AbstractModule
{
    public function __construct(
        private readonly int $delayMs = 5,
        AbstractModule|null $module = null,
    ) {
        parent::__construct($module);
    }

    protected function configure(): void
    {
        $this->bind()->annotatedWith('slowty_delay_ms')->toInstance($this->delayMs);
        $this->bind(RenderInterface::class)->to(SlowtyTemplateEngine::class);
    }
}
