<?php

declare(strict_types=1);

namespace BEAR\Async;

use BEAR\Resource\Annotation\Link;
use BEAR\Resource\DataLoader\DataLoader;
use BEAR\Resource\FactoryInterface;
use BEAR\Resource\InvokerInterface;
use BEAR\Resource\LinkCrawler;
use BEAR\Resource\LinkCrawlerInterface;
use BEAR\Resource\LinkType;
use BEAR\Resource\Method;
use BEAR\Resource\Request;
use BEAR\Resource\ResourceObject;
use BEAR\Resource\Uri;
use Override;
use ReflectionMethod;

use function array_map;
use function is_array;
use function ucfirst;
use function uri_template;

/**
 * Parallel (async) link crawler implementation
 *
 * This class processes crawl links by collecting all requests at each level
 * and executing them in parallel using the configured AsyncInterface adapter.
 *
 * The parallel crawl works level-by-level:
 * 1. First level: Users → all user requests execute in parallel
 * 2. Second level: Posts for each user → all post requests execute in parallel
 * 3. Third level: Comments for each post → all comment requests execute in parallel
 *
 * @psalm-import-type Body from \BEAR\Resource\Types
 * @psalm-import-type BodyList from \BEAR\Resource\Types
 * @psalm-import-type BodyOrStringList from \BEAR\Resource\Types
 * @psalm-import-type Query from \BEAR\Resource\Types
 * @psalm-import-type QueryList from \BEAR\Resource\Types
 */
final class AsyncLinkCrawler implements LinkCrawlerInterface
{
    /** @var array<string, RequestTask> Tasks already executed in this crawl, keyed by request hash */
    private array $tasks = [];

    /** @var array<string, true> Hashes whose nested links are already resolved (or in progress) */
    private array $resolvedHashes = [];

    public function __construct(
        private readonly InvokerInterface $invoker,
        private readonly FactoryInterface $factory,
        private readonly AsyncInterface $async,
        private readonly LinkCrawler $linkCrawler,
        private readonly DataLoader|null $dataLoader = null,
    ) {
    }

    #[Override]
    public function crawl(array $annotations, LinkType $link, array &$bodyList): void
    {
        // Dedup scope is one crawl. Linker resolves a fresh crawler per invoke,
        // so this only matters if the crawler is ever bound as a singleton
        $this->tasks = [];
        $this->resolvedHashes = [];

        // Process DataLoader-enabled links first
        /**
         * @psalm-suppress ArgumentTypeCoercion
         * @phpstan-ignore argument.type
         */
        $this->dataLoader?->load($annotations, $link, $bodyList);

        // Process level by level with async execution
        /**
         * @psalm-suppress MixedArgumentTypeCoercion
         * @phpstan-ignore argument.type
         */
        $this->processLevel($annotations, $link, $bodyList);
    }

    /**
     * Process one level of crawl requests in parallel, then recurse to next level
     *
     * @param list<Link>                       $annotations
     * @param array<int, array<string, mixed>> $bodyList
     *
     * @param-out array<int, array<string, mixed>> $bodyList
     */
    private function processLevel(array $annotations, LinkType $link, array &$bodyList): void
    {
        $batch = new RequestBatch();

        foreach ($bodyList as &$body) {
            $this->collectCrawlRequests($annotations, $link, $body, $batch);
        }

        unset($body);

        if ($batch->isEmpty()) {
            return;
        }

        // Execute all tasks in parallel using the async adapter
        ($this->async)($batch->getTasks());

        // Register executed tasks so dedup hits later in this crawl can reuse them
        foreach ($batch->getTasks() as $task) {
            $this->tasks[$task->getHash()] = $task;
        }

        // Process next level for all results
        foreach ($batch->getTasks() as $task) {
            $this->resolveNestedLinks($task, $link);
        }
    }

    /**
     * Collect crawl requests into batch (without executing)
     *
     * @param list<Link>           $annotations
     * @param array<string, mixed> $body
     */
    private function collectCrawlRequests(array $annotations, LinkType $link, array &$body, RequestBatch $batch): void
    {
        foreach ($annotations as $annotation) {
            if ($annotation->crawl !== $link->key) {
                continue;
            }

            // Skip DataLoader-enabled links (already processed by DataLoader)
            if ($annotation->dataLoader !== null && $this->dataLoader !== null) {
                continue;
            }

            $uri = uri_template($annotation->href, $body);
            $rel = $this->factory->newInstance($uri);
            $query = (new Uri($uri))->query;
            $request = new Request($this->invoker, $rel, Method::GET, $query);
            $hash = $request->hash();

            // Reuse the executed task for this hash, resolving its nested links first
            if (isset($this->tasks[$hash])) {
                $task = $this->tasks[$hash];
                $this->resolveNestedLinks($task, $link);
                /** @psalm-suppress PossiblyInvalidArrayAssignment */
                $body[$annotation->rel] = $task->getResult();

                continue;
            }

            // Add to batch for parallel execution
            $batch->add($request, $annotation->rel, $body);
        }
    }

    /**
     * Resolve this task's own crawl links and store the deep result
     *
     * No-op when the hash is already resolved, so every copy of a shared
     * resource (e.g. a diamond-shaped graph) carries the same nested data.
     */
    private function resolveNestedLinks(RequestTask $task, LinkType $link): void
    {
        $hash = $task->getHash();
        if (isset($this->resolvedHashes[$hash])) {
            return;
        }

        // Marked before descending: a cyclic link back here gets the shallow result
        $this->resolvedHashes[$hash] = true;

        $result = $task->getResult();
        if (! is_array($result)) {
            return;
        }

        // Determine if result is a list and process accordingly
        if ($result === []) {
            // Still need to trigger DataLoader for empty arrays
            $this->processEmptyResult($task, $link);

            return;
        }

        $resultList = $this->isList($result) ? $result : [$result];

        // Get the nested annotations for this result
        $request = $task->getRequest();
        $nestedAnnotations = $this->getLinkAnnotations($request->resourceObject, $request->method);

        // Check if there are any crawl annotations for this link
        $hasCrawlAnnotation = false;
        foreach ($nestedAnnotations as $annotation) {
            if ($annotation->crawl === $link->key) {
                $hasCrawlAnnotation = true;
                break;
            }
        }

        if (! $hasCrawlAnnotation) {
            return;
        }

        /** @var array<int, array<string, mixed>> $resultList */
        $this->processLevel($nestedAnnotations, $link, $resultList);

        // Update the result with nested data
        if ($this->isList($result)) {
            $task->setResult($resultList);

            return;
        }

        if (isset($resultList[0])) {
            $task->setResult($resultList[0]);
        }
    }

    /**
     * Process empty result to trigger DataLoader
     */
    private function processEmptyResult(RequestTask $task, LinkType $link): void
    {
        $request = $task->getRequest();
        $nestedAnnotations = $this->getLinkAnnotations($request->resourceObject, $request->method);

        // Trigger DataLoader without re-entering crawl(), which would reset the cache mid-crawl
        /** @var array<int, array<string, mixed>> $emptyList */
        $emptyList = [];
        /**
         * @psalm-suppress ArgumentTypeCoercion
         * @phpstan-ignore argument.type
         */
        $this->dataLoader?->load($nestedAnnotations, $link, $emptyList);
    }

    /**
     * Get Link annotations from a ResourceObject method using PHP 8 attributes
     *
     * @return list<Link>
     */
    private function getLinkAnnotations(ResourceObject $ro, Method $method): array
    {
        $classMethod = 'on' . ucfirst($method->value);
        $refMethod = new ReflectionMethod($ro, $classMethod);
        $attributes = $refMethod->getAttributes(Link::class);

        return array_map(
            static fn ($attr) => $attr->newInstance(),
            $attributes,
        );
    }

    /**
     * {@inheritDoc}
     */
    #[Override]
    public function isList(mixed $value): bool
    {
        return $this->linkCrawler->isList($value);
    }
}
