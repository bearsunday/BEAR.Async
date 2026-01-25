<?php

declare(strict_types=1);

namespace BEAR\Projection;

use BEAR\Async\SqlBatch;
use BEAR\Async\SqlBatchExecutorInterface;

use function file_get_contents;
use function ltrim;
use function spl_object_id;

final class QueryBatchCoordinator
{
    /** @var array<int, QueryResourceObject> */
    private array $resources = [];
    private bool $executed = false;

    public function __construct(
        private readonly SqlBatchExecutorInterface $executor,
        private readonly string $sqlDir,
    ) {
    }

    public function register(QueryResourceObject $resource): void
    {
        $id = spl_object_id($resource);
        $this->resources[$id] = $resource;
    }

    public function executeAll(): void
    {
        if ($this->executed || $this->resources === []) {
            return;
        }

        // クエリ収集 - uri は public
        $queries = [];
        foreach ($this->resources as $id => $resource) {
            $sqlName = ltrim($resource->uri->path, '/');
            $sqlFile = $this->sqlDir . '/' . $sqlName . '.sql';
            $sql = (string) file_get_contents($sqlFile);
            $queries[$id] = [$sql, $resource->uri->query];
        }

        // 一括実行
        $results = (new SqlBatch($this->executor, $queries))();

        // 結果配布 - body は public なので直接セット
        foreach ($this->resources as $id => $resource) {
            $resource->body = $results[$id] ?? [];
        }

        $this->executed = true;
    }

    public function clear(): void
    {
        $this->resources = [];
        $this->executed = false;
    }
}
