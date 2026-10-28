<?php

declare(strict_types=1);

namespace MyParcel\Shopware\Pdk\Order\Storage;

use Doctrine\DBAL\Connection;
use MyParcel\Shopware\Entity\OrderData\OrderDataEntity;
use MyParcel\Shopware\Entity\OrderExtension;
use MyParcel\Shopware\Entity\OrderShipment\OrderShipmentCollection;
use MyParcel\Shopware\Pdk\Order\Contract\OrderStorageInterface;
use MyParcel\Shopware\Pdk\Product\Contract\ProductStorageInterface;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Order\Aggregate\OrderAddress\OrderAddressEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * Reads orders with the DAL and writes the PDK's data to the plugin tables.
 *
 * Everything runs in the default context: the system scope in the default language.
 * The PDK endpoints already check the myparcel:access privilege.
 */
final class ShopwareOrderStorage implements OrderStorageInterface
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

    private bool $currencyChecked = false;

    /**
     * @param  EntityRepository<\Shopware\Core\Checkout\Order\OrderCollection>             $orderRepository
     * @param  EntityRepository<\MyParcel\Shopware\Entity\OrderData\OrderDataCollection>   $orderDataRepository
     * @param  EntityRepository<OrderShipmentCollection>                                   $orderShipmentRepository
     * @param  EntityRepository<\Shopware\Core\System\Currency\CurrencyCollection>         $currencyRepository
     */
    public function __construct(
        private readonly EntityRepository $orderRepository,
        private readonly EntityRepository $orderDataRepository,
        private readonly EntityRepository $orderShipmentRepository,
        private readonly EntityRepository $currencyRepository,
        private readonly ProductStorageInterface $productStorage,
        private readonly LoggerInterface $logger,
        private readonly Connection $connection
    ) {
    }

    public function getSnapshot(string $orderId): ?array
    {
        return $this->getSnapshots([$orderId])[$orderId] ?? null;
    }

    public function getSnapshots(array $orderIds): array
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

        $this->warnWhenDefaultCurrencyIsNotEuro();

        $productIds = [];

        /** @var OrderEntity $order */
        foreach ($orders as $order) {
            foreach ($order->getLineItems() ?? [] as $lineItem) {
                if (LineItem::PRODUCT_LINE_ITEM_TYPE === $lineItem->getType() && null !== $lineItem->getProductId()) {
                    $productIds[] = $lineItem->getProductId();
                }
            }
        }

        $products  = $this->productStorage->getSnapshots(array_values(array_unique($productIds)));
        $snapshots = [];

        foreach ($orders as $order) {
            $snapshots[$order->getId()] = $this->toSnapshot($order, $products);
        }

        return $snapshots;
    }

    public function findIdByApiIdentifier(string $apiIdentifier): ?string
    {
        $criteria = (new Criteria())->addFilter(new EqualsFilter('apiIdentifier', $apiIdentifier))->setLimit(1);

        /** @var null|OrderDataEntity $orderData */
        $orderData = $this->orderDataRepository->search($criteria, Context::createDefaultContext())->first();

        return $orderData?->getOrderId();
    }

    public function saveOrderData(string $orderId, array $data): void
    {
        $this->orderDataRepository->upsert([[
            'id'             => self::orderDataId($orderId),
            'orderId'        => $orderId,
            'orderVersionId' => Defaults::LIVE_VERSION,
            'apiIdentifier'  => $data['apiIdentifier'] ?? null,
            'data'           => $data,
        ]], Context::createDefaultContext());
    }

    public function getShipments(string $orderId): array
    {
        $criteria = (new Criteria())
            ->addFilter(new EqualsFilter('orderId', $orderId))
            ->addSorting(new FieldSorting('position'));

        /** @var OrderShipmentCollection $shipments */
        $shipments = $this->orderShipmentRepository->search($criteria, Context::createDefaultContext())->getEntities();

        return array_values(array_map(static fn ($shipment) => $shipment->getData(), $shipments->getElements()));
    }

    public function saveShipments(string $orderId, array $shipments): void
    {
        $context = Context::createDefaultContext();
        $rows    = [];

        foreach (array_values($shipments) as $position => $shipment) {
            $rows[] = [
                'id'             => Uuid::randomHex(),
                'orderId'        => $orderId,
                'orderVersionId' => Defaults::LIVE_VERSION,
                'position'       => $position,
                'data'           => $shipment,
            ];
        }

        // One transaction: a failed create must not leave the order without its stored shipments.
        $this->connection->transactional(function () use ($orderId, $rows, $context): void {
            $existing = $this->orderShipmentRepository
                ->searchIds((new Criteria())->addFilter(new EqualsFilter('orderId', $orderId)), $context)
                ->getIds();

            if ([] !== $existing) {
                $this->orderShipmentRepository->delete(array_map(static fn ($id) => ['id' => $id], array_values($existing)), $context);
            }

            if ([] !== $rows) {
                $this->orderShipmentRepository->create($rows, $context);
            }
        });
    }

    public function getNotes(string $orderId): array
    {
        if (! Uuid::isValid($orderId)) {
            return ['notes' => [], 'customerComment' => null];
        }

        $criteria = (new Criteria([$orderId]))->addAssociation(OrderExtension::ORDER_DATA);

        /** @var null|OrderEntity $order */
        $order = $this->orderRepository->search($criteria, Context::createDefaultContext())->first();

        /** @var null|OrderDataEntity $orderData */
        $orderData = $order?->getExtension(OrderExtension::ORDER_DATA);

        return ['notes' => $orderData?->getNotes() ?? [], 'customerComment' => $order?->getCustomerComment()];
    }

    public function saveNotes(string $orderId, array $notes): void
    {
        $this->orderDataRepository->upsert([[
            'id'             => self::orderDataId($orderId),
            'orderId'        => $orderId,
            'orderVersionId' => Defaults::LIVE_VERSION,
            'notes'          => array_values($notes),
        ]], Context::createDefaultContext());
    }

    /**
     * One row per order: a deterministic id makes every write an upsert without a
     * lookup first.
     */
    private static function orderDataId(string $orderId): string
    {
        return Uuid::fromStringToHex('myparcel_order_data.' . $orderId);
    }

    /**
     * @param  array<string, array<string, mixed>> $products
     *
     * @return array<string, mixed>
     */
    private function toSnapshot(OrderEntity $order, array $products): array
    {
        $delivery = self::delivery($order);

        /** @var null|OrderDataEntity $orderData */
        $orderData = $order->getExtension(OrderExtension::ORDER_DATA);

        /** @var null|OrderShipmentCollection $shipments */
        $shipments = $order->getExtension(OrderExtension::SHIPMENTS);

        $lines = [];

        foreach ($order->getLineItems() ?? [] as $lineItem) {
            $lines[] = [
                'type'          => $lineItem->getType(),
                'quantity'      => $lineItem->getQuantity(),
                'label'         => $lineItem->getLabel(),
                'productNumber' => $lineItem->getPayload()['productNumber'] ?? null,
                'price'         => null === $lineItem->getPrice()
                    ? ['unitPrice' => $lineItem->getUnitPrice(), 'quantity' => $lineItem->getQuantity(), 'taxes' => []]
                    : self::price($lineItem->getPrice()),
                'product'       => $products[$lineItem->getProductId() ?? ''] ?? null,
            ];
        }

        return [
            'id'              => $order->getId(),
            'orderNumber'     => $order->getOrderNumber(),
            'orderDateTime'   => $order->getOrderDateTime()->format(DATE_ATOM),
            'currencyFactor'  => $order->getCurrencyFactor(),
            'taxStatus'       => $order->getPrice()->getTaxStatus(),
            'customerComment' => $order->getCustomerComment(),
            'customerEmail'   => $order->getOrderCustomer()?->getEmail(),
            'shippingAddress' => self::address($delivery?->getShippingOrderAddress()),
            'billingAddress'  => self::address($order->getBillingAddress()),
            'shippingCosts'   => null === $delivery ? null : self::price($delivery->getShippingCosts()),
            'lines'           => $lines,
            'orderData'       => $orderData?->getData(),
            'notes'           => $orderData?->getNotes() ?? [],
            'shipments'       => null === $shipments
                ? []
                : array_values(array_map(static fn ($shipment) => $shipment->getData(), $shipments->getElements())),
        ];
    }

    /**
     * The primary delivery of the order. Only the checkout sets it in Shopware 6.7, so
     * for an order written through the DAL or the Admin API (an import), use the
     * delivery with the highest shipping costs, the rule of Shopware 6.8's backfill
     * (ties go to the lowest id).
     */
    private static function delivery(OrderEntity $order): ?OrderDeliveryEntity
    {
        $primary = $order->getPrimaryOrderDelivery();

        if (null !== $primary) {
            return $primary;
        }

        $chosen = null;

        foreach ($order->getDeliveries() ?? [] as $delivery) {
            if (null === $chosen) {
                $chosen = $delivery;

                continue;
            }

            $cost       = $delivery->getShippingCosts()->getTotalPrice();
            $chosenCost = $chosen->getShippingCosts()->getTotalPrice();

            if ($cost > $chosenCost || ($cost === $chosenCost && strcmp($delivery->getId(), $chosen->getId()) < 0)) {
                $chosen = $delivery;
            }
        }

        return $chosen;
    }

    /**
     * @return null|array<string, mixed>
     */
    private static function address(?OrderAddressEntity $address): ?array
    {
        if (null === $address) {
            return null;
        }

        return [
            'firstName'              => $address->getFirstName(),
            'lastName'               => $address->getLastName(),
            'company'                => $address->getCompany(),
            'street'                 => $address->getStreet(),
            'additionalAddressLine1' => $address->getAdditionalAddressLine1(),
            'additionalAddressLine2' => $address->getAdditionalAddressLine2(),
            'zipcode'                => $address->getZipcode(),
            'city'                   => $address->getCity(),
            'countryIso'             => $address->getCountry()?->getIso(),
            'stateName'              => $address->getCountryState()?->getName(),
            'phoneNumber'            => $address->getPhoneNumber(),
        ];
    }

    /**
     * @return array{unitPrice: float, quantity: int, taxes: list<array{tax: float, taxRate: float}>}
     */
    private static function price(CalculatedPrice $price): array
    {
        $taxes = [];

        foreach ($price->getCalculatedTaxes() as $tax) {
            $taxes[] = ['tax' => $tax->getTax(), 'taxRate' => $tax->getTaxRate()];
        }

        return ['unitPrice' => $price->getUnitPrice(), 'quantity' => $price->getQuantity(), 'taxes' => $taxes];
    }

    /**
     * The conversion to euro divides by the order's currency factor, which is
     * relative to the system default currency. Warn once per request when that
     * default is not EUR.
     */
    private function warnWhenDefaultCurrencyIsNotEuro(): void
    {
        if ($this->currencyChecked) {
            return;
        }

        $this->currencyChecked = true;
        $currency              = $this->currencyRepository->search(new Criteria([Defaults::CURRENCY]), Context::createDefaultContext())->first();

        if (null !== $currency && 'EUR' !== $currency->getIsoCode()) {
            $this->logger->warning('MyParcel: the default currency is not EUR, so order amounts are converted to the default currency and sent as euro');
        }
    }
}
