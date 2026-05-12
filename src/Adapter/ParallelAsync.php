<?php

declare(strict_types=1);

namespace BEAR\Async\Adapter;

use BEAR\AppMeta\AbstractAppMeta;
use BEAR\Async\AsyncInterface;
use BEAR\Async\AsyncRequest;
use BEAR\Async\Qualifier\Context;
use BEAR\Async\Qualifier\PoolSize;
use BEAR\Async\RequestTask;
use BEAR\Async\Worker\PayloadValidator;
use BEAR\Async\Worker\WorkerResourceCache;
use Override;
use parallel\Future;
use parallel\Runtime;
use Throwable;

use function array_keys;
use function array_values;
use function dirname;
use function extension_loaded;

/**
 * ext-parallel based async execution using thread pool
 *
 * This implementation uses PHP's parallel extension for true parallel
 * execution across multiple threads. Each Runtime lazily builds a single
 * ResourceInterface via WorkerResourceCache and reuses it across tasks.
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

    private readonly string $name;

    private readonly string $appDir;

    /** @param positive-int $poolSize Number of parallel runtimes (threads) */
    public function __construct(
        AbstractAppMeta $meta,
        #[Context]
        private readonly string $context,
        #[PoolSize]
        private readonly int $poolSize = 4,
    ) {
        $this->name = $meta->name;
        $this->appDir = $meta->appDir;
        $this->bootstrapFile = dirname(__DIR__, 2) . '/worker-bootstrap.php';
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
            PayloadValidator::assertCopyable($query, '$query');

            $future = $runtime->run(
                /**
                 * @param array<string, mixed> $query
                 *
                 * @return array<string, mixed>|null
                 */
                static function (string $name, string $context, string $appDir, string $uri, array $query): array|null {
                    $resource = WorkerResourceCache::getOrInit($name, $context, $appDir);
                    /** @var array<string, mixed> $query */
                    $ro = $resource->get($uri, $query);
                    /** @var array<string, mixed>|null $body */
                    $body = $ro->body;
                    PayloadValidator::assertCopyable($body, '$body');

                    return $body;
                },
                [$this->name, $this->context, $this->appDir, $uri, $query],
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
            PayloadValidator::assertCopyable($query, '$query');

            $future = $runtime->run(
                /** @param array<string, mixed> $query */
                static function (string $name, string $context, string $appDir, string $uri, array $query): string {
                    $resource = WorkerResourceCache::getOrInit($name, $context, $appDir);
                    /** @var array<string, mixed> $query */
                    $ro = $resource->get($uri, $query);

                    return (string) $ro;
                },
                [$this->name, $this->context, $this->appDir, $uri, $query],
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
        return [$asyncRequest->toUri(), $asyncRequest->query];
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
            try {
                $runtime->close();
            } catch (Throwable) {
                // Runtime may already be closed while PHP is shutting down.
            }
        }
    }
}
