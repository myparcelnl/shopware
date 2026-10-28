<?php

declare(strict_types=1);

namespace MyParcel\Shopware\Pdk\Order\Mapper;

use MyParcel\Shopware\Entity\OrderData\OrderDataEntity;
use MyParcel\Shopware\Entity\OrderExtension;
use MyParcel\Shopware\Entity\OrderShipment\OrderShipmentCollection;
use MyParcel\Shopware\Entity\OrderShipment\OrderShipmentEntity;
use MyParcelNL\Pdk\App\Order\Model\PdkOrder;
use MyParcelNL\Pdk\App\Order\Model\PdkProduct;
use MyParcelNL\Pdk\Facade\Logger;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryEntity;
use Shopware\Core\Checkout\Order\OrderEntity;

/**
 * Maps a Shopware order to a PdkOrder.
 *
 * Only product line items become order lines, like in the WooCommerce plugin:
 * weight and customs need products, not promotions or other line types. The
 * mapper skips the other lines itself, so a PdkOrder has the same lines wherever
 * the order comes from. The PDK computes the order totals from the lines and the
 * shipment price, so the mapper does not pass them. The mapper sets the notes, so
 * every access to PdkOrder::$notes gives the same collection and the PDK does not
 * read the notes again.
 */
final class PdkOrderMapper
{
    public function __construct(
        private readonly AddressMapper $addressMapper,
        private readonly MoneyConverter $moneyConverter
    ) {
    }

    /**
     * Maps one order, merged with the order data, notes and shipments that the
     * plugin stored for it. Use it for every order the PDK reads. The order needs
     * the associations that PdkOrderRepository loads.
     *
     * @param  array<string, PdkProduct> $products   the products of the order lines, keyed by product id
     * @param  float                     $euroFactor EuroCurrency::getFactor()
     */
    public function map(OrderEntity $order, array $products, float $euroFactor): PdkOrder
    {
        $currencyFactor = $order->getCurrencyFactor();
        $taxStatus      = $order->getPrice()->getTaxStatus();
        $email          = $order->getOrderCustomer()?->getEmail();
        $delivery       = self::findShippingDelivery($order);
        $shipping       = $this->addressMapper->map($delivery?->getShippingOrderAddress(), $email);
        $billing        = $this->addressMapper->map($order->getBillingAddress(), $email);

        if ($shipping->splitFailed) {
            Logger::warning(
                'MyParcel: could not find a house number in the shipping address; it is sent as one street',
                ['orderId' => $order->getId()]
            );
        }

        $shipmentPrice = null === $delivery
            ? ['price' => 0, 'vat' => 0, 'priceAfterVat' => 0]
            : $this->moneyConverter->toUnitCents($delivery->getShippingCosts(), $currencyFactor, $euroFactor, $taxStatus);

        /** @var null|OrderDataEntity $orderData */
        $orderData = $order->getExtension(OrderExtension::ORDER_DATA);

        /** @var null|OrderShipmentCollection $shipments */
        $shipments = $order->getExtension(OrderExtension::SHIPMENTS);

        $base = [
            'externalIdentifier'    => $order->getId(),
            'referenceIdentifier'   => $order->getOrderNumber(),
            'orderDate'             => $order->getOrderDateTime()->format(DATE_ATOM),
            'shippingAddress'       => $shipping->contactDetails,
            'billingAddress'        => $billing->contactDetails,
            'lines'                 => $this->mapLines($order, $products, $currencyFactor, $euroFactor, $taxStatus),
            'shipmentPrice'         => $shipmentPrice['price'],
            'shipmentVat'           => $shipmentPrice['vat'],
            'shipmentPriceAfterVat' => $shipmentPrice['priceAfterVat'],
        ];

        return new PdkOrder(array_replace(
            $base,
            $orderData?->getData() ?? [],
            [
                'shipments' => null === $shipments
                    ? []
                    : array_values(array_map(static fn (OrderShipmentEntity $shipment) => $shipment->getData(), $shipments->getElements())),
                'notes'     => OrderNotesBuilder::build(
                    $order->getId(),
                    $orderData?->getNotes() ?? [],
                    $order->getCustomerComment()
                ),
            ]
        ));
    }

    /**
     * The delivery that the shipping address and costs come from. Only the checkout
     * sets the primary delivery in Shopware 6.7, so for an order written through the
     * DAL or the Admin API (an import), take the delivery with the highest shipping
     * costs, the rule of Shopware 6.8's backfill (ties go to the lowest id).
     */
    private static function findShippingDelivery(OrderEntity $order): ?OrderDeliveryEntity
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
     * @param  array<string, PdkProduct> $products
     *
     * @return list<array<string, mixed>>
     */
    private function mapLines(OrderEntity $order, array $products, float $currencyFactor, float $euroFactor, string $taxStatus): array
    {
        $lines = [];

        foreach ($order->getLineItems() ?? [] as $lineItem) {
            if (LineItem::PRODUCT_LINE_ITEM_TYPE !== $lineItem->getType()) {
                continue;
            }

            $price = $lineItem->getPrice() ?? new CalculatedPrice(
                $lineItem->getUnitPrice(),
                $lineItem->getTotalPrice(),
                new CalculatedTaxCollection(),
                new TaxRuleCollection(),
                $lineItem->getQuantity()
            );

            // A deleted product is not in $products: keep the name and number that the
            // order line saved, without weight.
            $lines[] = array_merge(
                $this->moneyConverter->toUnitCents($price, $currencyFactor, $euroFactor, $taxStatus),
                [
                    'quantity' => $lineItem->getQuantity(),
                    'product'  => $products[$lineItem->getProductId() ?? ''] ?? [
                        'name'   => $lineItem->getLabel(),
                        'sku'    => ($lineItem->getPayload() ?? [])['productNumber'] ?? null,
                        'weight' => 0,
                    ],
                ]
            );
        }

        return $lines;
    }
}
