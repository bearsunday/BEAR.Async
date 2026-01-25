<?php

declare(strict_types=1);

namespace BEAR\Async\Mysqli;

use mysqli_stmt;

/**
 * Binds parameters to mysqli prepared statements
 */
final class MysqliParamBinder
{
    /**
     * Bind parameters to a mysqli prepared statement
     *
     * @param mysqli_stmt          $stmt   Prepared statement
     * @param array<string, mixed> $params Parameters to bind (associative array)
     * @param string               $sql    Original SQL with named placeholders
     */
    public function bind(mysqli_stmt $stmt, array $params, string $sql): void
    {
        if ($params === []) {
            return;
        }

        [$convertedSql, $orderedParams] = $this->convertNamedToPositional($sql, $params);
        unset($convertedSql);

        $types = $this->buildTypeString($orderedParams);
        $stmt->bind_param($types, ...$orderedParams);
    }

    /**
     * Convert named placeholders to positional placeholders
     *
     * @param string               $sql    SQL with named placeholders (:name)
     * @param array<string, mixed> $params Named parameters
     *
     * @return array{string, list<mixed>} Converted SQL and ordered parameter values
     *
     * @psalm-suppress MixedAssignment Parameter values are intentionally mixed
     */
    public function convertNamedToPositional(string $sql, array $params): array
    {
        // First, find all named placeholders in order
        preg_match_all('/:([a-zA-Z_][a-zA-Z0-9_]*)/', $sql, $matches);

        /** @var list<mixed> $orderedParams */
        $orderedParams = [];
        foreach ($matches[1] as $name) {
            $orderedParams[] = $params[$name] ?? null;
        }

        // Replace all named placeholders with positional ones
        $convertedSql = (string) preg_replace('/:([a-zA-Z_][a-zA-Z0-9_]*)/', '?', $sql);

        return [$convertedSql, $orderedParams];
    }

    /**
     * Build type string for mysqli bind_param
     *
     * @param list<mixed> $params Parameters to determine types for
     *
     * @psalm-suppress MixedAssignment Parameter values are intentionally mixed
     */
    public function buildTypeString(array $params): string
    {
        $types = '';
        foreach ($params as $param) {
            $types .= $this->getParamType($param);
        }

        return $types;
    }

    /**
     * Get mysqli type character for a value
     */
    private function getParamType(mixed $value): string
    {
        return match (true) {
            is_int($value) => 'i',
            is_float($value) => 'd',
            is_string($value) && $this->isBinaryString($value) => 'b',
            default => 's',
        };
    }

    /**
     * Check if string contains binary data
     */
    private function isBinaryString(string $value): bool
    {
        return preg_match('/[^\x20-\x7E\t\r\n]/', $value) === 1 && strlen($value) > 1000;
    }
}
