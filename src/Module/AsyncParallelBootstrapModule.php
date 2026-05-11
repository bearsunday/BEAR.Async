<?php

declare(strict_types=1);

namespace BEAR\Async\Module;

use BEAR\Async\Qualifier\Context;
use Override;
use Ray\Di\AbstractModule;

/**
 * Context-aware orchestrator for ext-parallel async execution.
 *
 * Binds the current application context (so `ParallelAsync` can pass it to
 * worker Runtimes when spawning them) and installs the mechanism module
 * `AsyncParallelModule`. This split lets `AsyncParallelModule` itself stay
 * context-unaware: only this module knows what context the application is
 * currently running under.
 *
 * Intended to be installed by the library-provided `bootstrap.php` from
 * the user's `bin/async.php` entrypoint. Not for direct installation in
 * `AppModule` — AppModule should be ignorant of execution form.
 *
 * @internal
 */
final class AsyncParallelBootstrapModule extends AbstractModule
{
    /**
     * @param non-empty-string  $context  Application context propagated to worker Runtimes (e.g., 'prod-hal-app')
     * @param positive-int|null $poolSize Worker pool size (null = autodetect CPU cores)
     */
    public function __construct(
        private readonly string $context,
        private readonly int|null $poolSize = null,
    ) {
        parent::__construct();
    }

    #[Override]
    protected function configure(): void
    {
        $this->bind()->annotatedWith(Context::class)->toInstance($this->context);
        $this->install(new AsyncParallelModule($this->poolSize));
    }
}
