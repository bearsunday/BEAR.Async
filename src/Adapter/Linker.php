<?php

declare(strict_types=1);

namespace BEAR\Async\Adapter;

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
use BEAR\Resource\Method;
use BEAR\Resource\Request;
use BEAR\Resource\ResourceObject;
use BEAR\Resource\Types;
use BEAR\Resource\Uri;
use Override;
use Ray\Aop\ReflectionMethod;

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
 * @psalm-import-type Body from Types
 * @psalm-import-type BodyOrStringList from Types
 * @psalm-import-type ObjectList from Types
 * @psalm-import-type Query from Types
 * @psalm-import-type QueryList from Types
 */
final class Linker implements LinkerInterface
{
    /**
     * memory cache for linker
     *
     * @var Query
     */
    private array $cache = [];

    public function __construct(
        private readonly InvokerInterface $invoker,
        private readonly FactoryInterface $factory,
        private readonly DataLoader|null $dataLoader = null,
    ) {
    }

    /**
     * {@inheritDoc}
     */
    #[Override]
    public function invoke(AbstractRequest $request)
    {
        $this->cache = [];

        return $this->invokeRecursive($request);
    }

    /**
     * @throws LinkQueryException
     * @throws LinkRelException
     */
    private function invokeRecursive(AbstractRequest $request): ResourceObject
    {
        $this->invoker->invoke($request);
        $current = clone $request->resourceObject;
        if ($current->code >= Code::BAD_REQUEST) {
            return $current;
        }

        foreach ($request->links as $link) {
            /** @var Body $nextBody */
            $nextBody = $this->annotationLink($link, $current, $request)->body;
            $current = $this->nextLink($link, $current, $nextBody);
        }

        return $current;
    }

    /**
     * @param Body $nextResource
     *
     * @return ResourceObject
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
     * Annotation link
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

        $classMethod = 'on' . ucfirst($request->method->value);
        /** @var list<Link> $annotations */
        $annotations = (new ReflectionMethod($current::class, $classMethod))->getAnnotations();
        if ($link->type === LinkType::CRAWL_LINK) {
            return $this->annotationCrawl($annotations, $link, $current);
        }

        /* @noinspection ExceptionsAnnotatingAndHandlingInspection */
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
        /* @noinspection LoopWhichDoesNotLoopInspection */
        foreach ($annotations as $annotation) {
            if ($annotation->rel !== $link->key) {
                continue;
            }

            $uri = uri_template($annotation->href, (array) $current->body);
            $rel = $this->factory->newInstance($uri);
            /* @noinspection UnnecessaryParenthesesInspection */
            $query = (new Uri($uri))->query;
            $request = new Request($this->invoker, $rel, Method::GET, $query);

            return $this->invoker->invoke($request);
        }

        throw new LinkRelException("rel:{$link->key} class:" . $current::class, 500);
    }

    /**
     * Link annotation crawl
     *
     * @param ObjectList $annotations
     *
     * @throws MethodException
     */
    private function annotationCrawl(array $annotations, LinkType $link, ResourceObject $current): ResourceObject
    {
        $isList = $this->isList($current->body);
        /** @var QueryList $bodyList */
        $bodyList = $isList ? (array) $current->body : [$current->body];

        // Process DataLoader-enabled links first
        $this->dataLoader?->load($annotations, $link, $bodyList);

        // Process non-DataLoader links
        /**
         * @psalm-suppress MixedAssignment
         * @psalm-suppress MixedArgument
         */
        foreach ($bodyList as &$body) {
            $this->crawl($annotations, $link, $body);
        }

        unset($body);
        /** @psalm-suppress PossiblyUndefinedArrayOffset, InvalidArrayAccess */
        $current->body = $isList ? $bodyList : $bodyList[0];

        return $current;
    }

    /**
     * @param ObjectList $annotations
     * @param Body       $body
     *
     * @throws LinkQueryException
     * @throws MethodException
     * @throws LinkRelException
     * @throws UriException
     *
     * @param-out Body $body
     */
    private function crawl(array $annotations, LinkType $link, array &$body): void
    {
        foreach ($annotations as $annotation) {
            if (! $annotation instanceof Link || $annotation->crawl !== $link->key) {
                continue;
            }

            // Skip DataLoader-enabled links (already processed by DataLoader)
            if ($annotation->dataLoader !== null && $this->dataLoader !== null) {
                continue;
            }

            $uri = uri_template($annotation->href, $body);
            $rel = $this->factory->newInstance($uri);
            /* @noinspection UnnecessaryParenthesesInspection */
            $query = (new Uri($uri))->query;
            $request = new Request($this->invoker, $rel, Method::GET, $query, [$link], $this);
            $hash = $request->hash();
            if (array_key_exists($hash, $this->cache)) {
                /** @var Body $cachedResponse */
                $cachedResponse = $this->cache[$hash];
                $body[$annotation->rel] = $cachedResponse;
                continue;
            }

            $this->cache[$hash] = $body[$annotation->rel] = $this->getResponseBody($request);
        }
    }

    /** @return Body|null */
    private function getResponseBody(Request $request): array|null
    {
        $body = $this->invokeRecursive($request)->body;
        assert(is_array($body) || $body === null);

        return $body;
    }

    private function isList(mixed $value): bool
    {
        assert(is_array($value));
        /** @var BodyOrStringList $list */
        $list = $value;
        /** @var Body $firstRow */
        $firstRow = array_pop($list);
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
