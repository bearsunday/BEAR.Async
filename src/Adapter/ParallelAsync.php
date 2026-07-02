<?php

declare(strict_types=1);

namespace BEAR\Async\Adapter;

use BEAR\AppMeta\AbstractAppMeta;
use BEAR\Async\AsyncInterface;
use BEAR\Async\AsyncRequest;
use BEAR\Async\Exception\TaskNotDispatchedException;
use BEAR\Async\Qualifier\Context;
use BEAR\Async\Qualifier\PoolSize;
use BEAR\Async\RequestTask;
use BEAR\Async\Worker\PayloadValidator;
use BEAR\Async\Worker\WorkerResourceCache;
use BEAR\Resource\LinkType;
use BEAR\Resource\Method;
use Override;
use parallel\Future;
use parallel\Runtime;
use Throwable;

use function array_key_first;
use function array_keys;
use function array_values;
use function dirname;
use function sprintf;

/**
 * ext-parallel based async execution using thread pool
 *
 * This implementation uses PHP's parallel extension for true parallel
 * execution across multiple threads. Each Runtime lazily builds a single
 * ResourceInterface via WorkerResourceCache and reuses it across tasks.
 *
 * Failure semantics: a Throwable raised while executing one task/request
 * does not abort its siblings. Every dispatched future is always joined
 * (its value()/exception is collected); once all futures have been joined,
 * the first Throwable encountered (in task/request iteration order) is
 * rethrown to the caller, preserving its original type. A task whose
 * Runtime::run() call returned null (never dispatched) is reported via
 * {@see TaskNotDispatchedException} rather than silently leaving its
 * result unset.
 *
 * Link replay: requests carrying linkSelf()/linkNew()/linkCrawl() have
 * those links replayed inside the worker via ResourceInterface::newRequest()
 * so the embedded resource graph matches what would happen synchronously.
 *
 * On first use, initializePool() warms exactly one worker synchronously
 * before any task is dispatched. This serializes the (expensive) cold DI
 * container build so the remaining pool threads hit a warm
 * WorkerResourceCache instead of all compiling the same app concurrently.
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

        /** @var array<int, Throwable> $errors */
        $errors = [];
        foreach ($futures as $i => $future) {
            try {
                /** @var array<string, mixed>|null $result */
                $result = $future->value();
                $taskList[$i]->setResult($result);
            } catch (Throwable $e) {
                $errors[$i] = $e;
            }
        }

        foreach ($taskList as $i => $task) {
            if (isset($futures[$i]) || isset($errors[$i])) {
                continue;
            }

            $uri = (string) $task->getRequest()->resourceObject->uri;
            $errors[$i] = new TaskNotDispatchedException(sprintf('Task not dispatched to worker Runtime for URI: %s', $uri));
        }

        if ($errors !== []) {
            throw $errors[array_key_first($errors)];
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
        $keys = array_keys($requests);
        $requestList = array_values($requests);

        foreach ($requestList as $i => $request) {
            $runtime = $this->pool[$i % $this->poolSize];
            [$uri, $query] = $this->extractUriAndQueryFromAsyncRequest($request);
            $links = $this->extractLinks($request);
            PayloadValidator::assertCopyable($query, '$query');
            PayloadValidator::assertCopyable($links, '$links');

            $future = $runtime->run(
                /**
                 * @param array<string, mixed>               $query
                 * @param list<array{key: string, type: string}> $links
                 */
                static function (string $name, string $context, string $appDir, string $uri, array $query, array $links): string {
                    $resource = WorkerResourceCache::getOrInit($name, $context, $appDir);
                    /** @var array<string, mixed> $query */
                    $request = $resource->newRequest(Method::GET, $uri, $query);
                    foreach ($links as $link) {
                        /** @var array{key: string, type: string} $link */
                        match ($link['type']) {
                            LinkType::SELF_LINK => $request->linkSelf($link['key']),
                            LinkType::NEW_LINK => $request->linkNew($link['key']),
                            LinkType::CRAWL_LINK => $request->linkCrawl($link['key']),
                            default => null,
                        };
                    }

                    $ro = $request();

                    return (string) $ro;
                },
                [$this->name, $this->context, $this->appDir, $uri, $query, $links],
            );
            if ($future !== null) {
                $futures[$i] = $future;
            }
        }

        $results = [];
        /** @var array<int, Throwable> $errors */
        $errors = [];
        foreach ($futures as $i => $future) {
            try {
                /** @var string $result */
                $result = $future->value();
                $results[$keys[$i]] = $result;
            } catch (Throwable $e) {
                $errors[$i] = $e;
            }
        }

        foreach ($requestList as $i => $request) {
            if (isset($futures[$i]) || isset($errors[$i])) {
                continue;
            }

            $errors[$i] = new TaskNotDispatchedException(sprintf('Task not dispatched to worker Runtime for URI: %s', $request->toUri()));
        }

        if ($errors !== []) {
            throw $errors[array_key_first($errors)];
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

    /**
     * Extract a copyable list of links from an AsyncRequest
     *
     * BEAR\Resource\LinkType objects cannot cross the ext-parallel thread
     * boundary, so they are flattened to plain arrays and replayed inside
     * the worker via linkSelf()/linkNew()/linkCrawl().
     *
     * @return list<array{key: string, type: string}>
     */
    private function extractLinks(AsyncRequest $asyncRequest): array
    {
        $links = [];
        foreach ($asyncRequest->links as $link) {
            $links[] = ['key' => $link->key, 'type' => $link->type];
        }

        return $links;
    }

    private function initializePool(): void
    {
        // A prior call may have left runtimes behind if warmup failed partway
        // (initialized stays false so the next call retries from scratch);
        // kill them first so failed attempts don't leak threads.
        foreach ($this->pool as $runtime) {
            $runtime->kill();
        }

        $this->pool = [];
        for ($i = 0; $i < $this->poolSize; $i++) {
            $this->pool[] = new Runtime($this->bootstrapFile);
        }

        // Serialize the cold DI build on one worker so the remaining pool
        // threads hit a warm WorkerResourceCache instead of all compiling
        // the same app concurrently (thundering herd on var/tmp/{context}/di).
        $future = $this->pool[0]->run(
            static function (string $name, string $context, string $appDir): bool {
                WorkerResourceCache::getOrInit($name, $context, $appDir);

                return true;
            },
            [$this->name, $this->context, $this->appDir],
        );
        if ($future === null) {
            throw new TaskNotDispatchedException('Task not dispatched to worker Runtime for pool warmup');
        }

        $future->value();
    }

    public function __destruct()
    {
        foreach ($this->pool as $runtime) {
            $runtime->kill();
        }
    }
}
