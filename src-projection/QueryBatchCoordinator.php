<?php

declare(strict_types=1);

namespace BEAR\Projection;

use BEAR\Async\SqlBatch;
use BEAR\Async\SqlBatchExecutorInterface;
use BEAR\Projection\Exception\SqlFileNotFoundException;

use function basename;
use function file_get_contents;
use function is_readable;
use function ltrim;
use function realpath;
use function spl_object_id;
use function sprintf;
use function str_contains;
use function str_starts_with;

final class QueryBatchCoordinator
{
    /** @var array<int, QueryResourceObject> */
    private array $resources = [];
    private bool $executed = false;
    private readonly string $canonicalSqlDir;

    public function __construct(
        private readonly SqlBatchExecutorInterface $executor,
        private readonly string $sqlDir,
    ) {
        $resolved = realpath($sqlDir);
        if ($resolved === false) {
            throw new SqlFileNotFoundException(sprintf('SQL directory not found: %s', $sqlDir));
        }

        $this->canonicalSqlDir = $resolved;
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

        $queries = [];
        foreach ($this->resources as $id => $resource) {
            $sql = $this->loadSqlFile($resource->uri->path);
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

    private function loadSqlFile(string $path): string
    {
        $sqlName = ltrim($path, '/');

        // Reject path traversal attempts
        if (str_contains($sqlName, '..') || str_contains($sqlName, "\0")) {
            throw new SqlFileNotFoundException(sprintf('Invalid SQL file path: %s', $sqlName));
        }

        // Use basename to extract only the filename, rejecting subdirectories
        $safeName = basename($sqlName);
        $sqlFile = $this->canonicalSqlDir . '/' . $safeName . '.sql';

        // Verify the resolved path is within sqlDir
        $realPath = realpath($sqlFile);
        if ($realPath === false || ! str_starts_with($realPath, $this->canonicalSqlDir . '/')) {
            throw new SqlFileNotFoundException(sprintf('SQL file not found: %s', $sqlFile));
        }

        if (! is_readable($realPath)) {
            throw new SqlFileNotFoundException(sprintf('SQL file not readable: %s', $realPath));
        }

        $content = file_get_contents($realPath);
        if ($content === false) {
            throw new SqlFileNotFoundException(sprintf('Failed to read SQL file: %s', $realPath));
        }

        return $content;
    }
}
