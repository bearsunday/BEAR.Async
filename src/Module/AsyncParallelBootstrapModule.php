<?php

declare(strict_types=1);

namespace BEAR\Async\Module;

use BEAR\Async\Qualifier\Context;
use InvalidArgumentException;
use Override;
use Ray\Di\AbstractModule;

use function sprintf;

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
     * @param string   $context  Application context propagated to worker Runtimes (e.g., 'prod-hal-app'); must be non-empty.
     * @param int|null $poolSize Worker pool size (null = autodetect CPU cores); must be positive when given.
     *
     * @throws InvalidArgumentException when $context is empty or $poolSize is < 1.
     */
    public function __construct(
        private readonly string $context,
        private readonly int|null $poolSize = null,
    ) {
        if ($this->context === '') {
            throw new InvalidArgumentException('Context must not be empty.');
        }

        if ($this->poolSize !== null && $this->poolSize < 1) {
            throw new InvalidArgumentException(sprintf('poolSize must be a positive integer, %d given.', $this->poolSize));
        }

        parent::__construct();
    }

    #[Override]
    protected function configure(): void
    {
        $this->bind()->annotatedWith(Context::class)->toInstance($this->context);
        $this->install(new AsyncParallelModule($this->poolSize));
    }
}
