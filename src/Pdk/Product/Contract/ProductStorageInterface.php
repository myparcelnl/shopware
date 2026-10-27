<?php

declare(strict_types=1);

namespace MyParcel\Shopware\Pdk\Product\Contract;

/**
 * Reads Shopware products as plain-array snapshots and stores their MyParcel
 * custom fields. Orders reuse the snapshots for their lines.
 */
interface ProductStorageInterface
{
    /**
     * Reads products with their parent for variants. Ids that do not exist are left
     * out. Use it for order lines and for PDK product actions.
     *
     * @param  list<string> $productIds
     *
     * @return array<string, array<string, mixed>> keyed by product id
     */
    public function getSnapshots(array $productIds): array;

    /**
     * Writes only the given myparcel_* custom fields. Other custom fields of the
     * product stay as they are. Use it when the PDK saves product settings.
     *
     * @param  array<string, null|int|string> $customFields only myparcel_* keys
     */
    public function saveSettings(string $productId, array $customFields): void;
}
