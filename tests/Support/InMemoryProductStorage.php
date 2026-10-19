<?php

declare(strict_types=1);

namespace MyParcel\Shopware\Tests\Support;

use MyParcel\Shopware\Pdk\Product\Contract\ProductStorageInterface;

/**
 * Keeps product snapshots in an array and records the saved custom fields.
 */
final class InMemoryProductStorage implements ProductStorageInterface
{
    /** @var array<string, array<string, mixed>> */
    public array $snapshots = [];

    /** @var array<string, array<string, null|int|string>> */
    public array $savedSettings = [];

    public function getSnapshots(array $productIds): array
    {
        return array_intersect_key($this->snapshots, array_flip($productIds));
    }

    public function saveSettings(string $productId, array $customFields): void
    {
        $this->savedSettings[$productId] = $customFields;
    }
}
