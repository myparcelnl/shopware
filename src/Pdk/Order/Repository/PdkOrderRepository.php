<?php

declare(strict_types=1);

namespace MyParcel\Shopware\Pdk\Order\Repository;

use MyParcel\Shopware\Entity\OrderExtension;
use MyParcel\Shopware\Entity\OrderShipment\OrderShipmentCollection;
use MyParcel\Shopware\Entity\OrderShipment\OrderShipmentEntity;
use MyParcel\Shopware\Pdk\Currency\EuroCurrency;
use MyParcel\Shopware\Pdk\Order\Mapper\PdkOrderMapper;
use MyParcel\Shopware\Pdk\Order\Writer\OrderDataWriter;
use MyParcelNL\Pdk\App\Order\Collection\PdkOrderCollection;
use MyParcelNL\Pdk\App\Order\Contract\PdkProductRepositoryInterface;
use MyParcelNL\Pdk\App\Order\Model\PdkOrder;
use MyParcelNL\Pdk\App\Order\Model\PdkProduct;
use MyParcelNL\Pdk\App\Order\Repository\AbstractPdkOrderRepository;
use MyParcelNL\Pdk\Base\Exception\ModelNotFoundException;
use MyParcelNL\Pdk\Base\Support\Utils;
use MyParcelNL\Pdk\Shipment\Collection\ShipmentCollection;
use MyParcelNL\Pdk\Shipment\Model\Shipment;
use MyParcelNL\Pdk\Storage\Contract\StorageInterface;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Order\OrderCollection;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * Reads Shopware orders as PdkOrders and stores what the PDK changes on them.
 *
 * The in-request cache key is the bare order id: the PDK's ExportOrderAction calls
 * save($order->externalIdentifier, $order) on this repository itself.
 *
 * Reads run in the system scope (Context::createDefaultContext()), so they do not
 * check the order:read privilege. The admin endpoint only checks myparcel:access;
 * the role that INT-1958 adds for it also needs order:read and product:read.
 */
final class PdkOrderRepository extends AbstractPdkOrderRepository
{
    private const ASSOCIATIONS = [
        'lineItems',
        'primaryOrderDelivery.shippingOrderAddress.country',
        'primaryOrderDelivery.shippingOrderAddress.countryState',
        'deliveries.shippingOrderAddress.country',
        'deliveries.shippingOrderAddress.countryState',
        'billingAddress.country',
        'billingAddress.countryState',
        'orderCustomer',
        OrderExtension::ORDER_DATA,
        OrderExtension::SHIPMENTS,
    ];

    /**
     * $storage is the PDK's in-request cache (AbstractRepository::retrieve() and
     * save()), not a Shopware store.
     *
     * @param  EntityRepository<OrderCollection> $orderRepository
     */
    public function __construct(
        StorageInterface $storage,
        private readonly EntityRepository $orderRepository,
        private readonly EuroCurrency $euroCurrency,
        private readonly PdkProductRepositoryInterface $productRepository,
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
        $orderId = self::resolveOrderId($id);

        return $this->retrieve($orderId, fn (): ?PdkOrder => $this->readOrders([$orderId])[$orderId] ?? null);
    }

    /**
     * Returns the order, or throws ModelNotFoundException.
     *
     * @param  mixed $id
     */
    public function findOrFail($id): PdkOrder
    {
        return $this->find($id) ?? throw new ModelNotFoundException(PdkOrder::class, [self::resolveOrderId($id)]);
    }

    /**
     * Returns the orders that exist, the same as getMany(). The PDK calls it
     * through ModelRepositoryInterface.
     */
    public function findAll(array $ids): PdkOrderCollection
    {
        return $this->getMany($ids);
    }

    /**
     * Returns the orders that exist, in one read for those not cached yet.
     *
     * @param  mixed $orderIds
     */
    public function getMany($orderIds): PdkOrderCollection
    {
        $ids     = array_map([self::class, 'resolveOrderId'], Utils::toArray($orderIds));
        $missing = array_values(array_filter($ids, fn (string $id) => ! $this->storage->has($id)));
        $read    = [] === $missing ? [] : $this->readOrders($missing);
        $orders  = [];

        foreach ($ids as $id) {
            $order = $this->retrieve($id, static fn (): ?PdkOrder => $read[$id] ?? null);

            if (null !== $order) {
                $orders[] = $order;
            }
        }

        return new PdkOrderCollection($orders);
    }

    /**
     * Finds the order that the MyParcel API knows under this identifier. Use it
     * when a webhook or an API response names an order.
     */
    public function getByApiIdentifier(string $uuid): ?PdkOrder
    {
        $criteria = (new Criteria())
            ->addFilter(new EqualsFilter(OrderExtension::ORDER_DATA . '.apiIdentifier', $uuid))
            ->setLimit(1);
        $orderId  = $this->orderRepository->searchIds($criteria, Context::createDefaultContext())->firstId();

        return null === $orderId ? null : $this->find($orderId);
    }

    /**
     * Stores the order data and the shipments. Stored shipments that the order no
     * longer carries stay; incoming shipments replace stored ones with the same id.
     */
    public function update(PdkOrder $order): PdkOrder
    {
        $orderId = (string) $order->externalIdentifier;

        $order->shipments = $this->mergeShipments($this->readStoredShipments($orderId), $order->shipments);

        $this->orderDataWriter->saveOrderData($orderId, $order->toStorableArray());
        $this->orderDataWriter->saveShipments($orderId, $order->shipments);

        return $this->save($orderId, $order);
    }

    /**
     * Reads the orders with everything the mapper needs, and the products of their
     * lines through the PDK product repository.
     *
     * @param  list<string> $orderIds
     *
     * @return array<string, PdkOrder> keyed by order id; unknown ids are left out
     */
    private function readOrders(array $orderIds): array
    {
        $ids = array_values(array_unique(array_filter($orderIds, [Uuid::class, 'isValid'])));

        if ([] === $ids) {
            return [];
        }

        $criteria = new Criteria($ids);

        foreach (self::ASSOCIATIONS as $association) {
            $criteria->addAssociation($association);
        }

        $criteria->getAssociation('lineItems')->addSorting(new FieldSorting('position'));
        $criteria->getAssociation(OrderExtension::SHIPMENTS)->addSorting(new FieldSorting('position'));

        $orders = $this->orderRepository->search($criteria, Context::createDefaultContext())->getEntities();

        if (0 === $orders->count()) {
            return [];
        }

        $productIds = [];

        /** @var OrderEntity $order */
        foreach ($orders as $order) {
            foreach ($order->getLineItems() ?? [] as $lineItem) {
                if (LineItem::PRODUCT_LINE_ITEM_TYPE === $lineItem->getType() && null !== $lineItem->getProductId()) {
                    $productIds[] = $lineItem->getProductId();
                }
            }
        }

        $products = [];

        /** @var PdkProduct $product */
        foreach ($this->productRepository->getProducts(array_values(array_unique($productIds))) as $product) {
            $products[(string) $product->externalIdentifier] = $product;
        }

        $euroFactor = $this->euroCurrency->getFactor();
        $mapped     = [];

        foreach ($orders as $order) {
            $mapped[$order->getId()] = $this->mapper->map($order, $products, $euroFactor);
        }

        return $mapped;
    }

    /**
     * The shipments that are stored now, not those of the cached order: another
     * request can have added one since.
     */
    private function readStoredShipments(string $orderId): ShipmentCollection
    {
        if (! Uuid::isValid($orderId)) {
            return new ShipmentCollection();
        }

        $criteria = (new Criteria([$orderId]))->addAssociation(OrderExtension::SHIPMENTS);
        $criteria->getAssociation(OrderExtension::SHIPMENTS)->addSorting(new FieldSorting('position'));

        /** @var null|OrderEntity $order */
        $order = $this->orderRepository->search($criteria, Context::createDefaultContext())->first();

        /** @var null|OrderShipmentCollection $shipments */
        $shipments = $order?->getExtension(OrderExtension::SHIPMENTS);

        return new ShipmentCollection(null === $shipments
            ? []
            : array_values(array_map(static fn (OrderShipmentEntity $shipment) => $shipment->getData(), $shipments->getElements())));
    }

    /**
     * A shipment without an id cannot be matched. The incoming one is the current
     * one, so stored shipments without an id are dropped and incoming ones kept.
     * Incoming shipments that the PDK marked deleted stay in the list, so
     * OrderDataWriter deletes their rows. An incoming copy wins over the stored copy
     * by id, so a request with an older copy of a shipment overwrites what another
     * request (for example a shipment webhook) stored in the meantime.
     *
     * @TODO INT-1962: keep the stored copy of a shipment when it is newer than the incoming one, so shipment webhooks do not lose updates.
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
    private static function resolveOrderId($input): string
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
