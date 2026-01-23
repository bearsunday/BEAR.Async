<?php

declare(strict_types=1);

namespace BEAR\Async;

use BEAR\Resource\AbstractRequest;
use BEAR\Resource\Annotation\Link;
use BEAR\Resource\Code;
use BEAR\Resource\DataLoader\DataLoader;
use BEAR\Resource\Exception;
use BEAR\Resource\Exception\LinkQueryException;
use BEAR\Resource\Exception\LinkRelException;
use BEAR\Resource\Exception\MethodException;
use BEAR\Resource\Exception\UriException;
use BEAR\Resource\FactoryInterface;
use BEAR\Resource\InvokerInterface;
use BEAR\Resource\LinkerInterface;
use BEAR\Resource\LinkType;
use BEAR\Resource\Request;
use BEAR\Resource\ResourceObject;
use BEAR\Resource\Uri;
use Ray\Aop\ReflectionMethod;
use ReflectionException;

use function array_filter;
use function array_key_exists;
use function array_keys;
use function array_pop;
use function assert;
use function count;
use function is_array;
use function is_numeric;
use function ucfirst;
use function uri_template;

/**
 * Async-enabled Linker that executes crawl requests in parallel
 *
 * This linker replaces the standard Linker to enable parallel execution
 * of crawl requests using the configured AsyncInterface adapter.
 *
 * The parallel crawl works level-by-level:
 * 1. First level: Users → all user requests execute in parallel
 * 2. Second level: Posts for each user → all post requests execute in parallel
 * 3. Third level: Comments for each post → all comment requests execute in parallel
 *
 * This provides significant performance improvement for nested crawls while
 * maintaining the same API and result structure.
 *
 * @psalm-import-type Body from \BEAR\Resource\Types
 * @psalm-import-type BodyOrStringList from \BEAR\Resource\Types
 * @psalm-import-type ObjectList from \BEAR\Resource\Types
 * @psalm-import-type Query from \BEAR\Resource\Types
 * @psalm-import-type QueryList from \BEAR\Resource\Types
 *
 * @codeCoverageIgnore Requires BEAR.Resource integration test
 */
final class AsyncLinker implements LinkerInterface
{
    /** @var array<string, array<string, mixed>|null> */
    private array $cache = [];

    public function __construct(
        private readonly InvokerInterface $invoker,
        private readonly FactoryInterface $factory,
        private readonly AsyncInterface $async,
        private readonly DataLoader|null $dataLoader = null,
    ) {
    }

    /** {@inheritDoc} */
    public function invoke(AbstractRequest $request): ResourceObject
    {
        $this->cache = [];
        $this->invoker->invoke($request);
        $current = clone $request->resourceObject;

        if ($current->code >= Code::BAD_REQUEST) {
            return $current;
        }

        foreach ($request->links as $link) {
            if ($link->type === LinkType::CRAWL_LINK) {
                $this->processCrawlAsync($link, $current, $request);
            } else {
                /** @var Body $nextBody */
                $nextBody = $this->annotationLink($link, $current, $request)->body;
                $current = $this->nextLink($link, $current, $nextBody);
            }
        }

        return $current;
    }

    /**
     * Process crawl link with async parallel execution
     *
     * @throws MethodException
     * @throws LinkQueryException
     */
    private function processCrawlAsync(LinkType $link, ResourceObject $current, AbstractRequest $request): void
    {
        if (! is_array($current->body)) {
            throw new Exception\LinkQueryException('Only array is allowed for link in ' . $current::class, 500);
        }

        $classMethod = 'on' . ucfirst($request->method);
        /** @var list<Link> $annotations */
        $annotations = (new ReflectionMethod($current::class, $classMethod))->getAnnotations();

        $isList = $this->isList($current->body);
        /** @var QueryList $bodyList */
        $bodyList = $isList ? (array) $current->body : [$current->body];

        // Process DataLoader-enabled links first
        $this->dataLoader?->load($annotations, $link, $bodyList);

        // Process level by level with async execution
        $this->processLevel($annotations, $link, $bodyList);

        /** @psalm-suppress PossiblyUndefinedArrayOffset, InvalidArrayAccess */
        $current->body = $isList ? $bodyList : $bodyList[0];
    }

    /**
     * Process one level of crawl requests in parallel, then recurse to next level
     *
     * @param ObjectList                 $annotations
     * @param list<array<string, mixed>> $bodyList
     *
     * @throws MethodException
     * @throws UriException
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
        foreach ($batch->getTasks() as $task) {
            $result = $task->getResult();
            if (! is_array($result)) {
                continue;
            }

            // Determine if result is a list and process accordingly
            $resultList = $this->isList($result) ? $result : [$result];

            // Get the nested annotations for this result
            $request = $task->getRequest();
            $classMethod = 'on' . ucfirst($request->method);

            try {
                /** @var list<Link> $nestedAnnotations */
                $nestedAnnotations = (new ReflectionMethod($request->resourceObject::class, $classMethod))->getAnnotations();
                /** @var list<array<string, mixed>> $resultList */
                $this->processLevel($nestedAnnotations, $link, $resultList);

                // Update the result with nested data
                if ($this->isList($result)) {
                    $task->setResult($resultList);
                } elseif (isset($resultList[0])) {
                    $task->setResult($resultList[0]);
                }
            } catch (ReflectionException) {
                // No nested annotations, skip
            }
        }
    }

    /**
     * Collect crawl requests into batch (without executing)
     *
     * @param ObjectList           $annotations
     * @param array<string, mixed> $body
     *
     * @throws MethodException
     * @throws UriException
     */
    private function collectCrawlRequests(array $annotations, LinkType $link, array &$body, RequestBatch $batch): void
    {
        foreach ($annotations as $annotation) {
            if (! $annotation instanceof Link || $annotation->crawl !== $link->key) {
                continue;
            }

            // Skip DataLoader-enabled links (already processed by DataLoader)
            // @phpstan-ignore function.impossibleType (backwards compatibility with older BEAR.Resource versions)
            $hasDataLoader = property_exists($annotation, 'dataLoader') && $annotation->dataLoader !== null;
            if ($hasDataLoader && $this->dataLoader !== null) {
                continue;
            }

            $uri = uri_template($annotation->href, $body);
            $rel = $this->factory->newInstance($uri);
            $query = (new Uri($uri))->query;
            $request = new Request($this->invoker, $rel, Request::GET, $query, [$link], $this);
            $hash = $request->hash();

            // Check cache first
            if (array_key_exists($hash, $this->cache)) {
                /** @var Body $cachedResponse */
                $cachedResponse = $this->cache[$hash];
                $body[$annotation->rel] = $cachedResponse;

                continue;
            }

            // Add to batch for parallel execution
            $batch->add($request, $annotation->rel, $body);
        }
    }

    /**
     * @param Body $nextResource
     */
    private function nextLink(LinkType $link, ResourceObject $ro, array $nextResource): ResourceObject
    {
        /** @psalm-suppress MixedAssignment */
        $nextBody = $nextResource;

        if ($link->type === LinkType::SELF_LINK) {
            $ro->body = $nextBody;

            return $ro;
        }

        if ($link->type === LinkType::NEW_LINK) {
            assert(is_array($ro->body) || $ro->body === null);
            $ro->body[$link->key] = $nextBody;

            return $ro;
        }

        // crawl
        return $ro;
    }

    /**
     * Annotation link for non-crawl links
     *
     * @throws MethodException
     * @throws LinkRelException
     * @throws Exception\LinkQueryException
     */
    private function annotationLink(LinkType $link, ResourceObject $current, AbstractRequest $request): ResourceObject
    {
        if (! is_array($current->body)) {
            throw new Exception\LinkQueryException('Only array is allowed for link in ' . $current::class, 500);
        }

        $classMethod = 'on' . ucfirst($request->method);
        /** @var list<Link> $annotations */
        $annotations = (new ReflectionMethod($current::class, $classMethod))->getAnnotations();

        return $this->annotationRel($annotations, $link, $current);
    }

    /**
     * Annotation link (new, self)
     *
     * @param Link[] $annotations
     *
     * @throws UriException
     * @throws MethodException
     * @throws Exception\LinkQueryException
     * @throws Exception\LinkRelException
     */
    private function annotationRel(array $annotations, LinkType $link, ResourceObject $current): ResourceObject
    {
        foreach ($annotations as $annotation) {
            if ($annotation->rel !== $link->key) {
                continue;
            }

            $uri = uri_template($annotation->href, (array) $current->body);
            $rel = $this->factory->newInstance($uri);
            $query = (new Uri($uri))->query;
            $request = new Request($this->invoker, $rel, Request::GET, $query);

            return $this->invoker->invoke($request);
        }

        throw new LinkRelException("rel:{$link->key} class:" . $current::class, 500);
    }

    private function isList(mixed $value): bool
    {
        if (! is_array($value) || $value === []) {
            return false;
        }

        /** @var Body $firstRow */
        $firstRow = end($value);
        /** @var BodyOrStringList $list */
        $list = array_slice($value, 0, -1, true);
        /** @var Query|string $firstRow */
        $keys = array_keys((array) $firstRow);
        $isMultiColumnMultiRowList = $this->isMultiColumnMultiRowList($keys, $list);
        $isMultiColumnList = $this->isMultiColumnList($value, $firstRow);
        $isSingleColumnList = $this->isSingleColumnList($value, $keys, $list);

        return $isSingleColumnList || $isMultiColumnMultiRowList || $isMultiColumnList;
    }

    /**
     * @param list<array-key>  $keys
     * @param BodyOrStringList $list
     */
    private function isMultiColumnMultiRowList(array $keys, array $list): bool
    {
        if ($keys === [0 => 0]) {
            return false;
        }

        foreach ($list as $item) {
            if ($keys !== array_keys((array) $item)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param Body $value
     * @psalm-param Query|scalar $firstRow
     */
    private function isMultiColumnList(array $value, mixed $firstRow): bool
    {
        return is_array($firstRow) && array_filter(array_keys($value), is_numeric(...)) === array_keys($value);
    }

    /**
     * @param Body            $value
     * @param list<array-key> $keys
     * @param Body            $list
     */
    private function isSingleColumnList(array $value, array $keys, array $list): bool
    {
        return (count($value) === 1) && $keys === array_keys($list);
    }
}
