<?php

declare(strict_types=1);

use MyParcel\Shopware\Checkout\CartDeliveryOptions;
use MyParcel\Shopware\Checkout\Subscriber\CartConvertedSubscriber;
use MyParcel\Shopware\Entity\OrderExtension;
use MyParcel\Shopware\Pdk\Order\Writer\OrderDataWriter;
use MyParcel\Shopware\Tests\Support\Checkout;
use MyParcel\Shopware\Tests\Support\Entities;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\Order\CartConvertedEvent;
use Shopware\Core\Checkout\Cart\Order\OrderConversionContext;

function cartConvertedEvent(Cart $cart, array $converted): CartConvertedEvent
{
    return new CartConvertedEvent($cart, $converted, Checkout::context(), new OrderConversionContext());
}

it('listens to the conversion of a cart to an order', function () {
    expect(CartConvertedSubscriber::getSubscribedEvents())->toBe([CartConvertedEvent::class => 'onCartConverted']);
});

it('writes the choice to the order data in the same write as the order', function () {
    $orderId = Entities::id();
    $cart    = new Cart(Checkout::TOKEN);
    CartDeliveryOptions::store($cart, ['carrier' => 'POSTNL', 'deliveryType' => 'pickup'], Checkout::context());
    $event = cartConvertedEvent($cart, ['id' => $orderId, 'customFields' => ['other' => 1]]);

    (new CartConvertedSubscriber())->onCartConverted($event);

    $converted = $event->getConvertedCart();

    expect($converted[OrderExtension::ORDER_DATA])->toBe([
        'id'   => OrderDataWriter::buildOrderDataId($orderId),
        'data' => ['deliveryOptions' => ['carrier' => 'POSTNL', 'deliveryType' => 'pickup']],
    ])
        // The order's own fields stay as they are.
        ->and($converted['customFields'])->toBe(['other' => 1]);
});

it('adds no order data for a cart without a choice', function () {
    $event = cartConvertedEvent(new Cart(Checkout::TOKEN), ['id' => Entities::id()]);

    (new CartConvertedSubscriber())->onCartConverted($event);

    expect($event->getConvertedCart())->not->toHaveKey(OrderExtension::ORDER_DATA);
});
