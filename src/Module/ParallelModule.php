<?php

declare(strict_types=1);

namespace BEAR\Async\Module;

use BEAR\Async\Adapter\ParallelAsync;
use BEAR\Async\AsyncInterface;
use BEAR\Async\AsyncLinkCrawler;
use BEAR\Async\Exception\ExtensionNotLoadedException;
use BEAR\Async\Exception\RecursiveWorkerSpawnException;
use BEAR\Async\Qualifier\PoolSize;
use BEAR\Async\Worker\WorkerResourceCache;
use BEAR\Resource\LinkCrawler;
use BEAR\Resource\LinkCrawlerInterface;
use InvalidArgumentException;
use Override;
use Ray\Di\AbstractModule;
use Ray\Di\Scope;

use function exec;
use function extension_loaded;
use function function_exists;
use function is_numeric;
use function php_uname;
use function sprintf;
use function str_starts_with;
use function trim;

/**
 * ParallelModule — ext-parallel mechanism bindings.
 *
 * Context-unaware: binds AsyncInterface to ParallelAsync, LinkCrawler to
 * AsyncLinkCrawler, the pool size qualifier, and installs AsyncEmbedModule.
 * The application context that worker Runtimes will execute under is supplied
 * separately by `ParallelRuntimeModule`.
 *
 * @internal
 *   Do not install directly in AppModule. Install via the library-provided
 *   bootstrap (`vendor/bear/async/bootstrap.php`) from `bin/async.php`, which
 *   installs `ParallelRuntimeModule` and overrides AppModule. If this
 *   module ends up loaded inside a worker Runtime, configure() throws
 *   `RecursiveWorkerSpawnException` to prevent recursive thread-pool spawn.
 */
final class ParallelModule extends AbstractModule
{
    /** @var positive-int */
    private readonly int $poolSize;

    /**
     * @param int|null $poolSize Worker pool size (null = autodetect CPU cores); must be positive when given.
     *
     * @throws InvalidArgumentException when $poolSize is < 1.
     */
    public function __construct(int|null $poolSize = null)
    {
        if ($poolSize !== null && $poolSize < 1) {
            throw new InvalidArgumentException(sprintf('poolSize must be a positive integer, %d given.', $poolSize));
        }

        $this->poolSize = $poolSize ?? self::detectCpuCores();

        parent::__construct();
    }

    #[Override]
    protected function configure(): void
    {
        if (WorkerResourceCache::isWorker()) {
            throw new RecursiveWorkerSpawnException(
                'ParallelModule was installed inside a worker Runtime. '
                . 'This would spawn a nested thread pool. Install via bin/async.php '
                . '+ vendor/bear/async/bootstrap.php so workers run a plain AppModule instead.',
            );
        }

        if (! extension_loaded('parallel')) {
            throw new ExtensionNotLoadedException('ext-parallel is required. Install with: pecl install parallel (requires PHP ZTS build)');
        }

        $this->bind()->annotatedWith(PoolSize::class)->toInstance($this->poolSize);
        // ParallelAsync must be singleton to reuse thread pool across requests
        $this->bind(AsyncInterface::class)->to(ParallelAsync::class)->in(Scope::SINGLETON);
        $this->bind(LinkCrawler::class);
        $this->bind(LinkCrawlerInterface::class)->to(AsyncLinkCrawler::class);

        // Install AsyncEmbedModule for parallel #[Embed] support
        $this->install(new AsyncEmbedModule());
    }

    /** @return positive-int */
    private static function detectCpuCores(): int
    {
        if (! function_exists('exec')) {
            return 4;
        }

        $os = php_uname('s');
        $command = str_starts_with($os, 'Darwin') ? 'sysctl -n hw.ncpu' : 'nproc';
        $result = exec($command);

        if (! is_numeric($result)) {
            return 4;
        }

        $cores = (int) trim($result);

        return $cores > 0 ? $cores : 4;
    }
}
