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

use function array_key_exists;
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
    /** @var array<string, array<mixed>|null> */
    private array $cache = [];

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
        // Process DataLoader-enabled links first
        /**
         * @psalm-suppress ArgumentTypeCoercion
         * @phpstan-ignore argument.type
         */
        $this->dataLoader?->load($annotations, $link, $bodyList);

        // Process level by level with async execution
        /** @psalm-suppress MixedArgumentTypeCoercion */
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

        // Update cache with results
        foreach ($batch->getTasks() as $task) {
            $this->cache[$task->getHash()] = $task->getResult();
        }

        // Process next level for all results
        $this->processNextLevel($batch, $link);
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

            // Check cache first
            if (array_key_exists($hash, $this->cache)) {
                /** @var array<mixed>|null $cachedResponse */
                $cachedResponse = $this->cache[$hash];
                /** @psalm-suppress PossiblyInvalidArrayAssignment */
                $body[$annotation->rel] = $cachedResponse;

                continue;
            }

            // Add to batch for parallel execution
            $batch->add($request, $annotation->rel, $body);
        }
    }

    /**
     * Process next level for all batch results
     */
    private function processNextLevel(RequestBatch $batch, LinkType $link): void
    {
        foreach ($batch->getTasks() as $task) {
            $result = $task->getResult();
            if (! is_array($result)) {
                continue;
            }

            // Determine if result is a list and process accordingly
            if ($result === []) {
                // Still need to trigger DataLoader for empty arrays
                $this->processEmptyResult($task, $link);

                continue;
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
                continue;
            }

            /** @var array<int, array<string, mixed>> $resultList */
            $this->processLevel($nestedAnnotations, $link, $resultList);

            // Update the result with nested data
            if ($this->isList($result)) {
                $task->setResult($resultList);
            } elseif (isset($resultList[0])) {
                $task->setResult($resultList[0]);
            }
        }
    }

    /**
     * Process empty result to trigger DataLoader
     */
    private function processEmptyResult(RequestTask $task, LinkType $link): void
    {
        $request = $task->getRequest();
        $nestedAnnotations = $this->getLinkAnnotations($request->resourceObject, $request->method);

        // Call crawl with empty list to trigger DataLoader
        /** @var array<int, array<string, mixed>> $emptyList */
        $emptyList = [];
        $this->crawl($nestedAnnotations, $link, $emptyList);
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
