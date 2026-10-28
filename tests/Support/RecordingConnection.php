<?php

declare(strict_types=1);

namespace MyParcel\Shopware\Tests\Support;

use Closure;
use Doctrine\DBAL\Connection;

/**
 * A DBAL connection without a database. transactional() runs the closure at once
 * and counts the call; executeStatement() records the SQL and its parameters.
 */
final class RecordingConnection extends Connection
{
    public int $transactions = 0;

    /** @var list<array{sql: string, params: array<mixed>}> */
    public array $statements = [];

    public function __construct()
    {
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
