<?php

declare(strict_types=1);

namespace MyParcel\Shopware\Tests\Support;

use Closure;
use Doctrine\DBAL\Connection;

/**
 * A DBAL connection without a database. transactional() runs the closure at once
 * and counts the call; executeStatement() records the SQL and its parameters;
 * fetchFirstColumn() records the query and returns the given column.
 */
final class RecordingConnection extends Connection
{
    public int $transactions = 0;

    /** @var list<array{sql: string, params: array<mixed>}> */
    public array $statements = [];

    /** @var list<array{sql: string, params: array<mixed>}> */
    public array $queries = [];

    /**
     * @param  list<mixed> $firstColumn the result of every fetchFirstColumn() call
     */
    public function __construct(private readonly array $firstColumn = [])
    {
    }

    public function fetchFirstColumn(string $query, array $params = [], array $types = []): array
    {
        $this->queries[] = ['sql' => $query, 'params' => $params];

        return $this->firstColumn;
    }

    public function transactional(Closure $func): mixed
    {
        $this->transactions++;

        return $func($this);
    }

    public function executeStatement(string $sql, array $params = [], array $types = []): int|string
    {
        $this->statements[] = ['sql' => $sql, 'params' => $params];

        return 0;
    }
}
