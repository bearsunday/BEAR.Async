<?php

declare(strict_types=1);

namespace BEAR\Async;

use BEAR\Resource\AbstractRequest;
use BEAR\Resource\HalLinker;
use BEAR\Resource\RenderInterface;
use BEAR\Resource\ResourceObject;
use Nocarrier\Hal;
use Override;
use Ray\Aop\ReflectionMethod;

use function array_values;
use function assert;
use function http_build_query;
use function is_array;
use function is_object;
use function is_scalar;
use function is_string;
use function json_decode;
use function method_exists;
use function parse_str;
use function parse_url;
use function spl_object_id;
use function ucfirst;

use const JSON_THROW_ON_ERROR;
use const PHP_EOL;
use const PHP_URL_QUERY;

/**
 * AsyncHalRenderer loads pending futures before HAL rendering
 *
 * This renderer triggers the loading phase by loading all collected
 * FutureResource objects in parallel before proceeding with standard HAL rendering.
 *
 * @psalm-import-type Body from \BEAR\Resource\Types
 * @psalm-import-type Query from \BEAR\Resource\Types
 * @psalm-import-type ResourceObjectBody from \BEAR\Resource\Types
 */
final readonly class AsyncHalRenderer implements RenderInterface
{
    private const MAX_EMBED_DEPTH = 10;

    public function __construct(
        private HalLinker $linker,
        private EmbedDataLoader $loader,
        private EmbedRequests $embedRequests,
    ) {
    }

    /** {@inheritDoc} */
    #[Override]
    public function render(ResourceObject $ro)
    {
        // Load all collected futures in parallel
        $this->loader->load($this->embedRequests);

        $this->renderHal($ro);
        $this->updateHeaders($ro);

        return (string) $ro->view;
    }

    /**
     * Render embedded resource with cycle detection
     *
     * @param array<int, bool> $visited Object IDs already visited
     */
    private function renderEmbedded(ResourceObject $ro, array $visited = []): string
    {
        $objectId = spl_object_id($ro);

        // Cycle detection
        if (isset($visited[$objectId])) {
            return '{}';
        }

        // Depth limit check
        if (count($visited) >= self::MAX_EMBED_DEPTH) {
            return '{}';
        }

        $visited[$objectId] = true;

        // Load futures for this embedded resource
        $this->loader->load($this->embedRequests);

        $this->renderHalWithVisited($ro, $visited);
        $this->updateHeaders($ro);

        return (string) $ro->view;
    }

    public function renderHal(ResourceObject $ro): void
    {
        $this->renderHalWithVisited($ro, []);
    }

    /** @param array<int, bool> $visited */
    private function renderHalWithVisited(ResourceObject $ro, array $visited): void
    {
        [$ro, $body] = $this->valuate($ro, $visited);
        $method = 'on' . ucfirst($ro->uri->method);
        $hasMethod = method_exists($ro, $method);
        $annotations = $hasMethod ? (new ReflectionMethod($ro, $method))->getAnnotations() : [];
        $hal = $this->getHal($ro->uri, $body, $annotations);
        $json = $hal->asJson(true);
        assert(is_string($json));
        $ro->view = $json . PHP_EOL;
        $ro->headers['Content-Type'] = 'application/hal+json';
    }

    /** @param array<int, bool> $visited */
    private function valuateElements(ResourceObject $ro, array $visited): void
    {
        assert(is_array($ro->body));
        foreach ($ro->body as $key => &$embedded) {
            // Handle FutureResource - await and render
            if ($embedded instanceof FutureResource) {
                $this->handleEmbedded($ro, $key, $embedded->await(), $visited);

                continue;
            }

            // Handle standard AbstractRequest
            if (! ($embedded instanceof AbstractRequest)) {
                continue;
            }

            $this->handleEmbedded($ro, $key, $embedded(), $visited);
        }
    }

    /** @param array<int, bool> $visited */
    private function handleEmbedded(ResourceObject $ro, int|string $key, ResourceObject $embeddedRo, array $visited): void
    {
        assert(is_array($ro->body));

        $isNotArray = ! isset($ro->body['_embedded']) || ! is_array($ro->body['_embedded']);
        if ($isNotArray) {
            $ro->body['_embedded'] = [];
        }

        assert(is_array($ro->body['_embedded']));

        // Different schema handling
        if ($this->isDifferentSchema($ro, $embeddedRo)) {
            $ro->body['_embedded'][$key] = $embeddedRo->body;
            unset($ro->body[$key]);

            return;
        }

        unset($ro->body[$key]);
        $view = $this->renderEmbedded($embeddedRo, $visited);
        $ro->body['_embedded'][$key] = json_decode($view, null, 512, JSON_THROW_ON_ERROR);
    }

    private function isDifferentSchema(ResourceObject $parentRo, ResourceObject $childRo): bool
    {
        return $parentRo->uri->scheme . $parentRo->uri->host !== $childRo->uri->scheme . $childRo->uri->host;
    }

    /**
     * @param Body          $body
     * @param array<object> $annotations
     */
    private function getHal(\BEAR\Resource\AbstractUri $uri, array $body, array $annotations): Hal
    {
        $query = $uri->query ? '?' . http_build_query($uri->query) : '';
        $path = $uri->path . $query;
        $selfLink = $this->linker->getReverseLink($path, $uri->query);
        $hal = new Hal($selfLink, $body);

        return $this->linker->addHalLink($body, array_values($annotations), $hal);
    }

    /**
     * @param array<int, bool> $visited
     *
     * @return ResourceObjectBody
     */
    private function valuate(ResourceObject $ro, array $visited): array
    {
        if (is_scalar($ro->body)) {
            $ro->body = ['value' => $ro->body];
        }

        if ($ro->body === null) {
            $ro->body = [];
        }

        if (is_object($ro->body)) {
            $ro->body = (array) $ro->body;
        }

        // Evaluate all requests in body
        $this->valuateElements($ro, $visited);
        assert(is_array($ro->body));

        return [$ro, $ro->body];
    }

    private function updateHeaders(ResourceObject $ro): void
    {
        $ro->headers['Content-Type'] = 'application/hal+json';
        if (! isset($ro->headers['Location'])) {
            return;
        }

        $location = $ro->headers['Location'];
        $queryString = parse_url($location, PHP_URL_QUERY);
        parse_str((string) $queryString, $query);
        /** @var Query $query */

        $ro->headers['Location'] = $this->linker->getReverseLink($location, $query);
    }
}
