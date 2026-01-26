<?php

declare(strict_types=1);

namespace BEAR\Async\Adapter;

use BEAR\Async\AsyncInterface;
use BEAR\Async\AsyncRequest;
use BEAR\Async\Exception\BootstrapFileException;
use BEAR\Async\Qualifier\AppDir;
use BEAR\Async\Qualifier\AppNamespace;
use BEAR\Async\Qualifier\Context;
use BEAR\Async\Qualifier\PoolSize;
use BEAR\Async\RequestTask;
use Override;
use parallel\Future;
use parallel\Runtime;

use function array_keys;
use function array_values;
use function extension_loaded;
use function file_exists;
use function file_put_contents;
use function sprintf;
use function sys_get_temp_dir;
use function tempnam;
use function unlink;

/**
 * ext-parallel based async execution using thread pool
 *
 * This implementation uses PHP's parallel extension for true parallel
 * execution across multiple threads. Each Runtime maintains its own
 * bootstrapped application instance for efficient reuse.
 *
 * Requirements:
 * - PHP built with ZTS (Zend Thread Safety)
 * - ext-parallel installed
 *
 * @codeCoverageIgnore Requires ext-parallel runtime
 */
final class ParallelAsync implements AsyncInterface
{
    /** @var list<Runtime> Runtime instances */
    private array $pool = [];

    private bool $initialized = false;

    private readonly string $bootstrapFile;

    /**
     * @param string       $namespace Application namespace (e.g., 'MyVendor\MyApp')
     * @param string       $context   Application context (e.g., 'prod-app')
     * @param string       $appDir    Application root directory
     * @param positive-int $poolSize  Number of parallel runtimes (threads)
     */
    public function __construct(
        #[AppNamespace] string $namespace,
        #[Context] string $context,
        #[AppDir] string $appDir,
        #[PoolSize] private readonly int $poolSize = 4,
    ) {
        $this->bootstrapFile = $this->createBootstrapFile($namespace, $context, $appDir);
    }

    private function createBootstrapFile(string $namespace, string $context, string $appDir): string
    {
        $autoloadFile = $appDir . '/vendor/autoload.php';
        $template = <<<'PHP'
<?php
require '%s';
$GLOBALS['__bear_async_resource'] = \BEAR\Package\Injector::getInstance('%s', '%s', '%s')->getInstance(\BEAR\Resource\ResourceInterface::class);
PHP;
        $content = sprintf($template, $autoloadFile, $namespace, $context, $appDir);
        $file = tempnam(sys_get_temp_dir(), 'bear_async_');
        if ($file === false) {
            throw new BootstrapFileException('Failed to create temporary bootstrap file');
        }

        $result = file_put_contents($file, $content);
        if ($result === false) {
            throw new BootstrapFileException(sprintf('Failed to write bootstrap file: %s', $file));
        }

        return $file;
    }

    #[Override]
    public function __invoke(array $tasks): void
    {
        if ($tasks === []) {
            return;
        }

        if (! $this->initialized) {
            $this->initializePool();
            $this->initialized = true;
        }

        /** @var list<Future> $futures */
        $futures = [];
        $taskList = array_values($tasks);

        foreach ($taskList as $i => $task) {
            $runtime = $this->pool[$i % $this->poolSize];
            [$uri, $query] = $this->extractUriAndQuery($task);

            $future = $runtime->run(
                /**
                 * @param array<string, mixed> $query
                 *
                 * @return array<string, mixed>|null
                 */
                static function (string $uri, array $query): array|null {
                    /** @var \BEAR\Resource\ResourceInterface $resource */
                    $resource = $GLOBALS['__bear_async_resource'];
                    /** @var array<string, mixed> $query */
                    $ro = $resource->get($uri, $query);

                    /** @var array<string, mixed>|null */
                    return $ro->body;
                },
                [$uri, $query],
            );
            if ($future !== null) {
                $futures[$i] = $future;
            }
        }

        foreach ($futures as $i => $future) {
            /** @var array<string, mixed>|null $result */
            $result = $future->value();
            $taskList[$i]->setResult($result);
        }
    }

    /** {@inheritDoc} */
    #[Override]
    public function execute(array $requests): array
    {
        if ($requests === []) {
            return [];
        }

        if (! $this->initialized) {
            $this->initializePool();
            $this->initialized = true;
        }

        /** @var list<Future> $futures */
        $futures = [];
        $uris = array_keys($requests);
        $requestList = array_values($requests);

        foreach ($requestList as $i => $request) {
            $runtime = $this->pool[$i % $this->poolSize];
            [$uri, $query] = $this->extractUriAndQueryFromAsyncRequest($request);

            $future = $runtime->run(
                /**
                 * @param array<string, mixed> $query
                 */
                static function (string $uri, array $query): string {
                    /** @var \BEAR\Resource\ResourceInterface $resource */
                    $resource = $GLOBALS['__bear_async_resource'];
                    /** @var array<string, mixed> $query */
                    $ro = $resource->get($uri, $query);

                    return (string) $ro;
                },
                [$uri, $query],
            );
            if ($future !== null) {
                $futures[$i] = $future;
            }
        }

        $results = [];
        foreach ($futures as $i => $future) {
            /** @var string $result */
            $result = $future->value();
            $results[$uris[$i]] = $result;
        }

        return $results;
    }

    /**
     * Extract URI and query from RequestTask
     *
     * @return array{0: string, 1: array<string, mixed>}
     */
    private function extractUriAndQuery(RequestTask $task): array
    {
        $request = $task->getRequest();
        $uri = (string) $request->resourceObject->uri;

        return [$uri, $request->query];
    }

    /**
     * Extract URI and query from AsyncRequest
     *
     * @return array{0: string, 1: array<string, mixed>}
     */
    private function extractUriAndQueryFromAsyncRequest(AsyncRequest $asyncRequest): array
    {
        return [$asyncRequest->uri, $asyncRequest->query];
    }

    #[Override]
    public function isAvailable(): bool
    {
        return extension_loaded('parallel');
    }

    private function initializePool(): void
    {
        for ($i = 0; $i < $this->poolSize; $i++) {
            $this->pool[] = new Runtime($this->bootstrapFile);
        }
    }

    public function __destruct()
    {
        foreach ($this->pool as $runtime) {
            $runtime->kill();
        }

        if (file_exists($this->bootstrapFile)) {
            @unlink($this->bootstrapFile);
        }
    }
}
