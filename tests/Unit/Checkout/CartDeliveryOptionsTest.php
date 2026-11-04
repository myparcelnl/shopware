<?php

declare(strict_types=1);

use MyParcel\Shopware\Checkout\CartDeliveryOptions;
use MyParcel\Shopware\Tests\Support\Checkout;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Shipping\ShippingMethodEntity;
use Shopware\Core\Framework\Struct\ArrayStruct;

it('stores the choice on the cart and reads it back', function () {
    $cart = new Cart(Checkout::TOKEN);

    CartDeliveryOptions::store($cart, ['carrier' => 'POSTNL', 'deliveryType' => 'evening'], Checkout::context());

    expect(CartDeliveryOptions::read($cart))->toBe(['carrier' => 'POSTNL', 'deliveryType' => 'evening'])
        ->and($cart->getExtension(CartDeliveryOptions::EXTENSION_NAME))->toBeInstanceOf(ArrayStruct::class);
});

it('reads nothing from a cart without a choice', function () {
    expect(CartDeliveryOptions::read(new Cart(Checkout::TOKEN)))->toBeNull();
});

it('keeps the choice through serialization, as the cart persister does', function () {
    $cart = new Cart(Checkout::TOKEN);
    CartDeliveryOptions::store($cart, ['carrier' => 'POSTNL'], Checkout::context());

    expect(CartDeliveryOptions::read(unserialize(serialize($cart))))->toBe(['carrier' => 'POSTNL']);
});

it('matches the shipping method and the country it was made for', function () {
    $cart = new Cart(Checkout::TOKEN);
    CartDeliveryOptions::store($cart, ['carrier' => 'POSTNL'], Checkout::context());

    expect(CartDeliveryOptions::matches($cart, Checkout::context()))->toBeTrue()
        ->and(CartDeliveryOptions::matches($cart, Checkout::context(['countryIso' => 'BE'])))->toBeFalse();
});

it('does not match another shipping method', function () {
    $cart = new Cart(Checkout::TOKEN);
    CartDeliveryOptions::store($cart, ['carrier' => 'POSTNL'], Checkout::context());

    $context = Checkout::context();
    $context->assign(['shippingMethod' => Checkout::shippingMethod('0190a1b2c3d4e5f60718293a4b5c5002', 'Mailbox')]);

    expect($context->getShippingMethod())->toBeInstanceOf(ShippingMethodEntity::class)
        ->and(CartDeliveryOptions::matches($cart, $context))->toBeFalse();
});

it('forgets the choice', function () {
    $cart = new Cart(Checkout::TOKEN);
    CartDeliveryOptions::store($cart, ['carrier' => 'POSTNL'], Checkout::context());

    CartDeliveryOptions::forget($cart);

    expect(CartDeliveryOptions::read($cart))->toBeNull();
});
