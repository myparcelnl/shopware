<?php

declare(strict_types=1);

namespace MyParcel\Shopware\Pdk\Order\Repository;

use MyParcel\Shopware\Pdk\Order\Contract\OrderStorageInterface;
use MyParcel\Shopware\Pdk\Order\Mapper\PdkOrderMapper;
use MyParcel\Shopware\Pdk\Order\Writer\OrderDataWriter;
use MyParcelNL\Pdk\App\Order\Collection\PdkOrderCollection;
use MyParcelNL\Pdk\App\Order\Model\PdkOrder;
use MyParcelNL\Pdk\App\Order\Repository\AbstractPdkOrderRepository;
use MyParcelNL\Pdk\Base\Exception\ModelNotFoundException;
use MyParcelNL\Pdk\Base\Support\Utils;
use MyParcelNL\Pdk\Shipment\Collection\ShipmentCollection;
use MyParcelNL\Pdk\Shipment\Model\Shipment;
use MyParcelNL\Pdk\Storage\Contract\StorageInterface;

/**
 * Reads Shopware orders as PdkOrders and stores what the PDK changes on them.
 *
 * The in-request cache key is the bare order id: the PDK's ExportOrderAction calls
 * save($order->externalIdentifier, $order) on this repository itself.
 */
final class PdkOrderRepository extends AbstractPdkOrderRepository
{
    public function __construct(
        StorageInterface $storage,
        private readonly OrderStorageInterface $orderStorage,
        private readonly PdkOrderMapper $mapper,
        private readonly OrderDataWriter $orderDataWriter
    ) {
        parent::__construct($storage);
    }

    /**
     * Returns the order, or throws when it does not exist. Accepts an id, an
     * array with externalIdentifier, or a PdkOrder.
     *
     * @param  mixed $input
     */
    public function get($input): PdkOrder
    {
        return $this->findOrFail($input);
    }

    /**
     * Returns the order, or null when there is no order with this id.
     *
     * @param  mixed $id
     */
    public function find($id): ?PdkOrder
    {
        $orderId = self::toId($id);

        return $this->retrieve($orderId, function () use ($orderId): ?PdkOrder {
            $snapshot = $this->orderStorage->getSnapshot($orderId);

            return null === $snapshot ? null : $this->mapper->fromSnapshot($snapshot);
        });
    }

    /**
     * Returns the order, or throws ModelNotFoundException.
     *
     * @param  mixed $id
     */
    public function findOrFail($id): PdkOrder
    {
        return $this->find($id) ?? throw new ModelNotFoundException(PdkOrder::class, [self::toId($id)]);
    }

    /**
     * @param  array<mixed> $ids
     */
    public function findAll(array $ids): PdkOrderCollection
    {
        return $this->getMany($ids);
    }

    /**
     * Returns the orders that exist, in one storage read for those not cached yet.
     *
     * @param  mixed $orderIds
     */
    public function getMany($orderIds): PdkOrderCollection
    {
        $ids       = array_map([self::class, 'toId'], Utils::toArray($orderIds));
        $missing   = array_values(array_filter($ids, fn (string $id) => ! $this->storage->has($id)));
        $snapshots = [] === $missing ? [] : $this->orderStorage->getSnapshots($missing);
        $orders    = [];

        foreach ($ids as $id) {
            $order = $this->retrieve($id, function () use ($id, $snapshots): ?PdkOrder {
                return isset($snapshots[$id]) ? $this->mapper->fromSnapshot($snapshots[$id]) : null;
            });

            if (null !== $order) {
                $orders[] = $order;
            }
        }

        return new PdkOrderCollection($orders);
    }

    /**
     * Finds the order that the MyParcel API knows under this identifier.
     */
    public function getByApiIdentifier(string $uuid): ?PdkOrder
    {
        $orderId = $this->orderStorage->findIdByApiIdentifier($uuid);

        return null === $orderId ? null : $this->find($orderId);
    }

    /**
     * Stores the order data and the shipments. Stored shipments that the order no
     * longer carries stay; incoming shipments replace stored ones with the same id.
     */
    public function update(PdkOrder $order): PdkOrder
    {
        $orderId = (string) $order->externalIdentifier;

        $order->shipments = $this->mergeShipments(
            new ShipmentCollection($this->orderStorage->getShipments($orderId)),
            $order->shipments
        );

        $this->orderDataWriter->saveOrderData($orderId, $order->toStorableArray());
        $this->orderDataWriter->saveShipments($orderId, $order->shipments);

        return $this->save($orderId, $order);
    }

    /**
     * A shipment without an id cannot be matched. The incoming one is the current
     * one, so stored shipments without an id are dropped and incoming ones kept.
     * Incoming shipments that the PDK marked deleted stay in the list, so
     * OrderDataWriter deletes their rows.
     */
    private function mergeShipments(ShipmentCollection $stored, ShipmentCollection $incoming): ShipmentCollection
    {
        $byId      = [];
        $withoutId = [];

        /** @var Shipment $shipment */
        foreach ($stored as $shipment) {
            if (null !== $shipment->id) {
                $byId[$shipment->id] = $shipment;
            }
        }

        /** @var Shipment $shipment */
        foreach ($incoming as $shipment) {
            if (null === $shipment->id) {
                $withoutId[] = $shipment;
            } else {
                $byId[$shipment->id] = $shipment;
            }
        }

        return new ShipmentCollection(array_merge(array_values($byId), $withoutId));
    }

    /**
     * @param  mixed $input
     */
    private static function toId($input): string
    {
        if ($input instanceof PdkOrder) {
            return (string) $input->externalIdentifier;
        }

        if (is_array($input)) {
            return (string) ($input['externalIdentifier'] ?? $input['order_id'] ?? $input['id'] ?? '');
        }

        return (string) $input;
    }
}
