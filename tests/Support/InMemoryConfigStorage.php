<?php

declare(strict_types=1);

namespace MyParcel\Shopware\Tests\Support;

use MyParcel\Shopware\Pdk\Storage\ConfigStorageInterface;

/**
 * Keeps values in an array, so a test can see what a repository persisted and
 * hand the same store to a second repository to simulate a new request.
 */
final class InMemoryConfigStorage implements ConfigStorageInterface
{
    /**
     * @var array<string, mixed>
     */
    public array $values = [];

    public function get(string $key): mixed
    {
        return $this->values[$key] ?? null;
    }

    public function set(string $key, mixed $value): void
    {
        $this->values[$key] = $value;
    }

    public function delete(string $key): void
    {
        unset($this->values[$key]);
    }
}
