<?php

declare(strict_types=1);

namespace MyParcel\Shopware\Pdk\Order\Writer;

use Doctrine\DBAL\Connection;
use MyParcel\Shopware\Entity\OrderData\OrderDataCollection;
use MyParcel\Shopware\Entity\OrderShipment\OrderShipmentCollection;
use MyParcel\Shopware\Entity\OrderShipment\OrderShipmentEntity;
use MyParcelNL\Pdk\Shipment\Collection\ShipmentCollection;
use MyParcelNL\Pdk\Shipment\Model\Shipment;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * Writes what the PDK changes on an order to the plugin tables: the order data and
 * the notes to myparcel_order_data, the shipments to myparcel_order_shipment.
 *
 * Writes run in the system scope (Context::createDefaultContext()), always on the
 * live version of the order. Every row has a fixed id derived from the order (and
 * the shipment), so a write is an upsert without a lookup first.
 */
final class OrderDataWriter
{
    /**
     * @param  EntityRepository<OrderDataCollection>     $orderDataRepository
     * @param  EntityRepository<OrderShipmentCollection> $orderShipmentRepository
     */
    public function __construct(
        private readonly EntityRepository $orderDataRepository,
        private readonly EntityRepository $orderShipmentRepository,
        private readonly Connection $connection
    ) {
    }

    /**
     * Replaces the stored order data of the order, and stores its apiIdentifier for
     * PdkOrderRepository::getByApiIdentifier(). Creates the row when there is none.
     * Use it after the PDK changed an order.
     *
     * @param  array<string, mixed> $data PdkOrder::toStorableArray()
     */
    public function saveOrderData(string $orderId, array $data): void
    {
        $this->orderDataRepository->upsert([[
            'id'             => self::buildOrderDataId($orderId),
            'orderId'        => $orderId,
            'orderVersionId' => Defaults::LIVE_VERSION,
            'apiIdentifier'  => $data['apiIdentifier'] ?? null,
            'data'           => $data,
        ]], Context::createDefaultContext());
    }

    /**
     * Replaces the stored notes of the order. Creates the row when there is none.
     * Use it after the PDK added, changed or removed a note.
     *
     * @param  list<array<string, mixed>> $notes PdkOrderNote::toStorableArray() per note
     */
    public function saveNotes(string $orderId, array $notes): void
    {
        $this->orderDataRepository->upsert([[
            'id'             => self::buildOrderDataId($orderId),
            'orderId'        => $orderId,
            'orderVersionId' => Defaults::LIVE_VERSION,
            'notes'          => array_values($notes),
        ]], Context::createDefaultContext());
    }

    /**
     * Stores the shipments of the order. Use it after the PDK changed shipments.
     *
     * A shipment with an id keeps one row, with an id from the order id and the
     * shipment id, so its created_at stays and two requests that save the same
     * shipment write the same row. A shipment that the PDK marked deleted loses its
     * row. A stored shipment that is not in the list keeps its row: a list that a
     * request read before another request added a shipment must not delete it. A
     * shipment without an id cannot be matched; its row id comes from its position,
     * and the rows of such shipments are replaced on each save.
     */
    public function saveShipments(string $orderId, ShipmentCollection $shipments): void
    {
        $rows       = [];
        $deletedIds = [];
        $position   = 0;

        /** @var Shipment $shipment */
        foreach ($shipments as $shipment) {
            if (null !== $shipment->deleted) {
                if (null !== $shipment->id) {
                    $deletedIds[] = self::buildShipmentRowId($orderId, (string) $shipment->id);
                }

                continue;
            }

            $rows[] = [
                'id'             => null === $shipment->id
                    ? self::buildNewShipmentRowId($orderId, $position)
                    : self::buildShipmentRowId($orderId, (string) $shipment->id),
                'orderId'        => $orderId,
                'orderVersionId' => Defaults::LIVE_VERSION,
                'position'       => $position,
                'data'           => $shipment->toStorableArray(),
            ];

            $position++;
        }

        $context = Context::createDefaultContext();

        // One transaction: the stale rows and the new rows change together.
        $this->connection->transactional(function () use ($orderId, $rows, $deletedIds, $context): void {
            $staleIds = $this->findStaleShipmentRowIds($orderId, array_column($rows, 'id'), $deletedIds, $context);

            if ([] !== $staleIds) {
                $this->orderShipmentRepository->delete(array_map(static fn (string $id) => ['id' => $id], $staleIds), $context);
            }

            if ([] !== $rows) {
                $this->orderShipmentRepository->upsert($rows, $context);
            }
        });
    }

    /**
     * The stored rows of the order to delete: those of the deleted shipments, and
     * those of shipments without an id that this save does not write again.
     *
     * @param  list<string> $keptIds
     * @param  list<string> $deletedIds
     *
     * @return list<string>
     */
    private function findStaleShipmentRowIds(string $orderId, array $keptIds, array $deletedIds, Context $context): array
    {
        $criteria = (new Criteria())->addFilter(new EqualsFilter('orderId', $orderId));
        $staleIds = [];

        /** @var OrderShipmentEntity $row */
        foreach ($this->orderShipmentRepository->search($criteria, $context)->getEntities() as $row) {
            $withoutId = null === ($row->getData()['id'] ?? null);

            if (in_array($row->getId(), $deletedIds, true) || ($withoutId && !in_array($row->getId(), $keptIds, true))) {
                $staleIds[] = $row->getId();
            }
        }

        return $staleIds;
    }

    private static function buildOrderDataId(string $orderId): string
    {
        return Uuid::fromStringToHex('myparcel_order_data.' . $orderId);
    }

    private static function buildShipmentRowId(string $orderId, string $shipmentId): string
    {
        return Uuid::fromStringToHex($orderId . '.' . $shipmentId);
    }

    private static function buildNewShipmentRowId(string $orderId, int $position): string
    {
        return Uuid::fromStringToHex($orderId . '.new.' . $position);
    }
}
