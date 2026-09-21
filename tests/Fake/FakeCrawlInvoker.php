<?php

declare(strict_types=1);

namespace BEAR\Async\Fake;

use BEAR\Resource\AbstractRequest;
use BEAR\Resource\InvokerInterface;
use BEAR\Resource\ResourceObject;

use function ucfirst;

/**
 * Invokes the request method on its resource object, recording every request URI
 */
final class FakeCrawlInvoker implements InvokerInterface
{
    /** @var list<string> */
    public array $invoked = [];

    public function invoke(AbstractRequest $request): ResourceObject
    {
        $ro = $request->resourceObject;
        $this->invoked[] = (string) $ro->uri;
        $method = 'on' . ucfirst($request->method->value);

        return $ro->{$method}(...$request->query);
    }
}
