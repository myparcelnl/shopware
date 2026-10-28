<?php

declare(strict_types=1);

namespace MyParcel\Shopware\Tests\Support;

use DateTimeImmutable;
use MyParcel\Shopware\Entity\OrderData\OrderDataEntity;
use MyParcel\Shopware\Entity\OrderExtension;
use MyParcel\Shopware\Entity\OrderShipment\OrderShipmentCollection;
use MyParcel\Shopware\Entity\OrderShipment\OrderShipmentEntity;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\Price\Struct\CartPrice;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTax;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderAddress\OrderAddressEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderCustomer\OrderCustomerEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\DataAbstractionLayer\Pricing\Price;
use Shopware\Core\Framework\DataAbstractionLayer\Pricing\PriceCollection;
use Shopware\Core\System\Country\Aggregate\CountryState\CountryStateEntity;
use Shopware\Core\System\Country\CountryEntity;
use Shopware\Core\System\Currency\CurrencyEntity;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineState\StateMachineStateEntity;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineTransition\StateMachineTransitionEntity;

/**
 * Builds real Shopware entities with synthetic data for the unit tests. The
 * defaults describe one Dutch order of 2 x "Testproduct" with a promotion line,
 * shipping costs, stored PDK data and one stored shipment.
 */
final class Entities
{
    public const ORDER_ID   = '0190a1b2c3d4e5f60718293a4b5c6d01';
    public const PRODUCT_ID = '0190a1b2c3d4e5f60718293a4b5c0001';

    /**
     * A random id in the format of Uuid::randomHex(), which needs ramsey/uuid.
     */
    public static function id(): string
    {
        return bin2hex(random_bytes(16));
    }

    /**
     * An order address. Keys: firstName, lastName, company, street,
     * additionalAddressLine1, additionalAddressLine2, zipcode, city, countryIso,
     * stateName, phoneNumber.
     *
     * @param  array<string, null|string> $values
     */
    public static function address(array $values = []): OrderAddressEntity
    {
        $values = array_replace([
            'firstName'              => 'Jan',
            'lastName'               => 'Test',
            'company'                => null,
            'street'                 => 'Teststraat 12A',
            'additionalAddressLine1' => null,
            'additionalAddressLine2' => null,
            'zipcode'                => '1234 AB',
            'city'                   => 'Teststad',
            'countryIso'             => 'NL',
            'stateName'              => null,
            'phoneNumber'            => '+31 6 00000000',
        ], $values);

        $address = (new OrderAddressEntity())->assign([
            'id'                     => self::id(),
            'firstName'              => $values['firstName'],
            'lastName'               => $values['lastName'],
            'company'                => $values['company'],
            'street'                 => $values['street'],
            'additionalAddressLine1' => $values['additionalAddressLine1'],
            'additionalAddressLine2' => $values['additionalAddressLine2'],
            'zipcode'                => $values['zipcode'],
            'city'                   => $values['city'],
            'phoneNumber'            => $values['phoneNumber'],
        ]);

        if (null !== $values['countryIso']) {
            $address->setCountry((new CountryEntity())->assign(['id' => self::id(), 'iso' => $values['countryIso']]));
        }

        if (null !== $values['stateName']) {
            $address->setCountryState((new CountryStateEntity())->assign(['id' => self::id(), 'name' => $values['stateName']]));
        }

        return $address;
    }

    /**
     * A calculated price with one 21% tax of $tax over the whole position.
     */
    public static function price(float $unitPrice, int $quantity = 1, float $tax = 0.0): CalculatedPrice
    {
        $total = round($unitPrice * $quantity, 2);

        return new CalculatedPrice(
            $unitPrice,
            $total,
            new CalculatedTaxCollection([new CalculatedTax($tax, 21.0, $total)]),
            new TaxRuleCollection(),
            $quantity
        );
    }

    /**
     * An order line. Keys: id, type, quantity, label, productId, productNumber,
     * price. The default is 2 x "Testproduct" at 12.10 gross.
     *
     * @param  array<string, mixed> $values
     */
    public static function lineItem(array $values = []): OrderLineItemEntity
    {
        $values = array_replace([
            'id'            => self::id(),
            'type'          => LineItem::PRODUCT_LINE_ITEM_TYPE,
            'quantity'      => 2,
            'label'         => 'Testproduct',
            'productId'     => self::PRODUCT_ID,
            'productNumber' => 'SW-1',
            'price'         => self::price(12.10, 2, 4.20),
        ], $values);

        $lineItem = new OrderLineItemEntity();
        $lineItem->setId($values['id']);
        $lineItem->setType($values['type']);
        $lineItem->setQuantity($values['quantity']);
        $lineItem->setLabel($values['label']);
        $lineItem->setProductId($values['productId']);
        $lineItem->setPayload(null === $values['productNumber'] ? [] : ['productNumber' => $values['productNumber']]);
        $lineItem->setPrice($values['price']);

        if (null !== $values['price']) {
            $lineItem->setUnitPrice($values['price']->getUnitPrice());
            $lineItem->setTotalPrice($values['price']->getTotalPrice());
        }

        return $lineItem;
    }

    /**
     * A delivery to this address with these shipping costs.
     */
    public static function delivery(?OrderAddressEntity $address, CalculatedPrice $shippingCosts, ?string $id = null): OrderDeliveryEntity
    {
        $delivery = new OrderDeliveryEntity();
        $delivery->setId($id ?? self::id());
        $delivery->setShippingCosts($shippingCosts);

        if (null !== $address) {
            $delivery->setShippingOrderAddress($address);
        }

        return $delivery;
    }

    /**
     * An order. Keys: id, orderNumber, orderDateTime, currencyFactor, taxStatus,
     * customerEmail, customerComment, billingAddress, primaryDelivery, deliveries,
     * lineItems, orderData, notes, shipments, state. Without "deliveries", the
     * primary delivery is the only delivery.
     *
     * @param  array<string, mixed> $values
     */
    public static function order(array $values = []): OrderEntity
    {
        $id     = $values['id'] ?? self::ORDER_ID;
        $values = array_replace([
            'orderNumber'     => '10001',
            'orderDateTime'   => '2026-10-01T12:00:00+00:00',
            'currencyFactor'  => 1.0,
            'taxStatus'       => CartPrice::TAX_STATE_GROSS,
            'customerEmail'   => 'test@example.test',
            'customerComment' => 'Graag bij de buren afgeven',
            'billingAddress'  => self::address(['phoneNumber' => null]),
            'primaryDelivery' => self::delivery(self::address(), self::price(4.95, 1, 0.86)),
            'lineItems'       => [
                self::lineItem(),
                self::lineItem([
                    'type'          => LineItem::PROMOTION_LINE_ITEM_TYPE,
                    'quantity'      => 1,
                    'label'         => 'Korting',
                    'productId'     => null,
                    'productNumber' => null,
                    'price'         => self::price(-2.00, 1, -0.35),
                ]),
            ],
            'orderData'       => [
                'apiIdentifier'   => 'api-uuid-0001',
                'exported'        => true,
                'deliveryOptions' => ['carrier' => 'POSTNL', 'deliveryType' => 'standard', 'packageType' => 'package'],
            ],
            'notes'           => [],
            'shipments'       => [['id' => 101, 'carrier' => 'POSTNL', 'orderId' => $id, 'barcode' => '3STEST0000001']],
            'state'           => null,
        ], $values);

        $order = new OrderEntity();
        $order->setId($id);
        $order->setOrderNumber($values['orderNumber']);
        $order->setOrderDateTime(new DateTimeImmutable($values['orderDateTime']));
        $order->setCurrencyFactor($values['currencyFactor']);
        $order->setPrice(CartPrice::createEmpty($values['taxStatus']));
        $order->setCustomerComment($values['customerComment']);
        $order->setLineItems(new OrderLineItemCollection($values['lineItems']));
        $order->setPrimaryOrderDelivery($values['primaryDelivery']);
        $order->setDeliveries(new OrderDeliveryCollection(
            $values['deliveries'] ?? array_filter([$values['primaryDelivery']])
        ));

        if (null !== $values['customerEmail']) {
            $order->setOrderCustomer((new OrderCustomerEntity())->assign(['id' => self::id(), 'email' => $values['customerEmail']]));
        }

        if (null !== $values['billingAddress']) {
            $order->setBillingAddress($values['billingAddress']);
        }

        if (null !== $values['state']) {
            $order->setStateMachineState(self::state($values['state']));
        }

        if (null !== $values['orderData'] || [] !== $values['notes']) {
            $order->addExtension(OrderExtension::ORDER_DATA, (new OrderDataEntity())->assign([
                'id'      => self::id(),
                'orderId' => $id,
                'data'    => $values['orderData'],
                'notes'   => $values['notes'],
            ]));
        }

        $shipments = [];

        foreach (array_values($values['shipments']) as $position => $data) {
            $shipments[] = self::shipment($id, $position, $data);
        }

        $order->addExtension(OrderExtension::SHIPMENTS, new OrderShipmentCollection($shipments));

        return $order;
    }

    /**
     * A stored shipment row of an order.
     *
     * @param  array<string, mixed> $data Shipment::toStorableArray()
     */
    public static function shipment(string $orderId, int $position, array $data, ?string $id = null): OrderShipmentEntity
    {
        return (new OrderShipmentEntity())->assign([
            'id'             => $id ?? self::id(),
            'orderId'        => $orderId,
            'orderVersionId' => Defaults::LIVE_VERSION,
            'position'       => $position,
            'data'           => $data,
        ]);
    }

    /**
     * A product. Keys: id, parentId, productNumber, ean, name, gross (price in the
     * default currency), weight (kg), width, height, length (mm), type,
     * customFields, parent.
     *
     * @param  array<string, mixed> $values
     */
    public static function product(array $values = []): ProductEntity
    {
        $values = array_replace([
            'id'            => self::PRODUCT_ID,
            'parentId'      => null,
            'productNumber' => 'SW-1',
            'ean'           => '8710000000001',
            'name'          => 'Testproduct',
            'gross'         => 12.10,
            'weight'        => 0.25,
            'width'         => 100.0,
            'height'        => 50.0,
            'length'        => 205.0,
            'type'          => ProductDefinition::TYPE_PHYSICAL,
            'customFields'  => [],
            'parent'        => null,
        ], $values);

        $product = (new ProductEntity())->assign([
            'id'            => $values['id'],
            'parentId'      => $values['parentId'],
            'productNumber' => $values['productNumber'],
            'ean'           => $values['ean'],
            'name'          => $values['name'],
            'weight'        => $values['weight'],
            'width'         => $values['width'],
            'height'        => $values['height'],
            'length'        => $values['length'],
            'type'          => $values['type'],
            'customFields'  => $values['customFields'],
        ]);

        if (null !== $values['gross']) {
            $product->setPrice(new PriceCollection([
                new Price(Defaults::CURRENCY, round($values['gross'] / 1.21, 2), $values['gross'], false),
            ]));
        }

        if (null !== $values['parent']) {
            $product->setParent($values['parent']);
        }

        return $product;
    }

    /**
     * A currency with this ISO code and factor (the rate from the default currency).
     */
    public static function currency(string $isoCode, float $factor): CurrencyEntity
    {
        return (new CurrencyEntity())->assign(['id' => self::id(), 'isoCode' => $isoCode, 'factor' => $factor]);
    }

    /**
     * A state of a state machine.
     */
    public static function state(string $technicalName): StateMachineStateEntity
    {
        return (new StateMachineStateEntity())->assign(['id' => self::id(), 'technicalName' => $technicalName]);
    }

    /**
     * A state machine transition from one state to another.
     */
    public static function transition(string $action, string $from, string $to): StateMachineTransitionEntity
    {
        $transition = (new StateMachineTransitionEntity())->assign(['id' => self::id(), 'actionName' => $action]);
        $transition->setFromStateMachineState(self::state($from));
        $transition->setToStateMachineState(self::state($to));

        return $transition;
    }
}
