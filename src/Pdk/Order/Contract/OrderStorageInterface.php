<?php

declare(strict_types=1);

namespace MyParcel\Shopware\Pdk\Order\Contract;

/**
 * Reads Shopware orders as plain-array snapshots and stores the PDK's order data,
 * notes and shipments. A seam of our own: the unit tests run without Shopware, and
 * the PDK container reaches the Shopware implementation through PdkBootstrapper.
 */
interface OrderStorageInterface
{
    /**
     * @return null|array<string, mixed> null when there is no order with this id
     */
    public function getSnapshot(string $orderId): ?array;

    /**
     * @param  list<string> $orderIds
     *
     * @return array<string, array<string, mixed>> keyed by order id; unknown ids are left out
     */
    public function getSnapshots(array $orderIds): array;

    public function findIdByApiIdentifier(string $apiIdentifier): ?string;

    /**
     * @param  array<string, mixed> $data PdkOrder::toStorableArray()
     */
    public function saveOrderData(string $orderId, array $data): void;

    /**
     * @return list<array<string, mixed>> Shipment::toStorableArray() per shipment, in stored order
     */
    public function getShipments(string $orderId): array;

    /**
     * Replaces all stored shipments of the order with this list.
     *
     * @param  list<array<string, mixed>> $shipments
     */
    public function saveShipments(string $orderId, array $shipments): void;

    /**
     * @return array{notes: list<array<string, mixed>>, customerComment: ?string}
     */
    public function getNotes(string $orderId): array;

    /**
     * @param  list<array<string, mixed>> $notes
     */
    public function saveNotes(string $orderId, array $notes): void;
}
