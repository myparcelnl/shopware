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
     * Reads one order with its lines, addresses, product snapshots and the stored PDK
     * data. Use it to build a PdkOrder.
     *
     * @return null|array<string, mixed> null when there is no order with this id
     */
    public function getSnapshot(string $orderId): ?array;

    /**
     * Reads many orders like getSnapshot(), in one read. Use it instead of calling
     * getSnapshot() in a loop.
     *
     * @param  list<string> $orderIds
     *
     * @return array<string, array<string, mixed>> keyed by order id; unknown ids are left out
     */
    public function getSnapshots(array $orderIds): array;

    /**
     * Finds the Shopware order id for the identifier the MyParcel API gave the order.
     * Use it when a webhook or an API response names an order. Returns null for an
     * unknown identifier.
     */
    public function findIdByApiIdentifier(string $apiIdentifier): ?string;

    /**
     * Replaces the stored order data of the order and stores its apiIdentifier for
     * lookup by findIdByApiIdentifier(). Creates the row when there is none. Use it
     * after the PDK changed an order.
     *
     * @param  array<string, mixed> $data PdkOrder::toStorableArray()
     */
    public function saveOrderData(string $orderId, array $data): void;

    /**
     * Returns the stored shipments of the order. Use it to merge new shipments into
     * the stored ones before saveShipments().
     *
     * @return list<array<string, mixed>> Shipment::toStorableArray() per shipment, in stored order
     */
    public function getShipments(string $orderId): array;

    /**
     * Replaces all stored shipments of the order with this list. Use it after merging
     * the stored shipments with the PDK's changed ones; deleted shipments are already
     * left out of the list.
     *
     * @param  list<array<string, mixed>> $shipments
     */
    public function saveShipments(string $orderId, array $shipments): void;

    /**
     * Returns the stored PDK notes of the order, and the customer comment of the
     * order. The customer comment is never stored by the plugin. Use it to show the
     * notes of an order.
     *
     * @return array{notes: list<array<string, mixed>>, customerComment: ?string}
     */
    public function getNotes(string $orderId): array;

    /**
     * Replaces the stored notes list of the order. Use it after the PDK added,
     * changed or removed a note.
     *
     * @param  list<array<string, mixed>> $notes
     */
    public function saveNotes(string $orderId, array $notes): void;
}
