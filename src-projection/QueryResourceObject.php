<?php

declare(strict_types=1);

namespace BEAR\Projection;

use BEAR\Resource\AbstractRequest;
use BEAR\Resource\InvokerInterface;
use BEAR\Resource\InvokeRequestInterface;
use BEAR\Resource\ResourceObject;
use Override;

class QueryResourceObject extends ResourceObject implements InvokeRequestInterface
{
    public function __construct(
        private readonly QueryBatchCoordinator $coordinator,
    ) {
        $coordinator->register($this);
    }

    /**
     * HttpResourceObject と同じパターン
     */
    #[Override]
    public function _invokeRequest(InvokerInterface $invoker, AbstractRequest $request): ResourceObject
    {
        unset($invoker);

        return $this->request($request);
    }

    /**
     * リクエスト実行 - coordinator 経由でバッチ実行
     */
    public function request(AbstractRequest $request): ResourceObject
    {
        // URI とクエリは request から取得
        $this->uri = $request->resourceObject->uri;

        // 全 QueryResourceObject を一括実行
        $this->coordinator->executeAll();

        return $this;
    }
}
