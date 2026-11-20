<?php

declare(strict_types=1);

use MyParcel\Shopware\Checkout\SalesChannelContextHolder;
use MyParcel\Shopware\Pdk\Cart\Repository\PdkCartRepository;
use MyParcel\Shopware\Tests\Support\Checkout;
use MyParcel\Shopware\Tests\Support\Entities;
use MyParcel\Shopware\Tests\Support\FakeEntityRepository;
use MyParcel\Shopware\Tests\Support\FixedCartService;
use MyParcel\Shopware\Tests\Support\PdkServices;
use MyParcel\Shopware\Tests\Support\TestPdk;
use MyParcelNL\Pdk\App\Cart\Repository\AbstractPdkCartRepository;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\Price\Struct\CartPrice;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

beforeEach(fn () => TestPdk::boot());

function cartRepository(
    ?SalesChannelContext $context = null,
    ?FixedCartService $cartService = null,
    ?FakeEntityRepository $products = null
): PdkCartRepository {
    $holder = new SalesChannelContextHolder();

    if (null !== $context) {
        $holder->set($context);
    }

    return PdkServices::cartRepository($holder, $cartService, $products);
}

it('is a pdk cart repository', function () {
    expect(cartRepository())->toBeInstanceOf(AbstractPdkCartRepository::class);
});

it('reads the cart of the current request for null, as the checkout context action asks', function () {
    $context = Checkout::context();
    $cartService  = new FixedCartService(Checkout::cart());

    $cart = cartRepository($context, $cartService)->get(null);

    expect($cartService->contexts)->toBe([$context])
        ->and($cart->externalIdentifier)->toBe(Checkout::TOKEN);
});

it('maps the product lines with their products, in euro cents per unit', function () {
    $line = cartRepository(Checkout::context())->get(null)->lines->first();

    expect($line->quantity)->toBe(2)
        ->and($line->price)->toBe(1000)
        ->and($line->vat)->toBe(210)
        ->and($line->priceAfterVat)->toBe(1210)
        ->and($line->product->sku)->toBe('SW-1');
});

it('keeps a line whose product is gone, with its label and no weight', function () {
    $cart = cartRepository(Checkout::context(), null, FakeEntityRepository::with())->get(null);

    expect($cart->lines->first()->product->externalIdentifier)->toBe(Entities::PRODUCT_ID)
        ->and($cart->lines->first()->product->name)->toBe('Testproduct')
        ->and($cart->lines->first()->product->weight)->toBe(0);
});

it('takes the net shipping price, which the pdk adds to the delivery option prices', function () {
    $cart = cartRepository(Checkout::context())->get(null);

    expect($cart->shipmentPrice)->toBe(500)
        ->and($cart->shipmentVat)->toBe(105)
        ->and($cart->shipmentPriceAfterVat)->toBe(605);
});

it('reads the shipping costs as net when the cart is net', function () {
    $cartService = new FixedCartService(Checkout::cart(['shippingCosts' => 5.00, 'taxStatus' => CartPrice::TAX_STATE_NET]));

    $cart = cartRepository(Checkout::context(['taxState' => CartPrice::TAX_STATE_NET]), $cartService)->get(null);

    expect($cart->shipmentPrice)->toBe(500)
        ->and($cart->shipmentPriceAfterVat)->toBe(605);
});

it('gives the chosen shipping method and the shipping address', function () {
    $method = cartRepository(Checkout::context())->get(null)->shippingMethod;

    expect($method->id)->toBe(Checkout::SHIPPING_METHOD_ID)
        ->and($method->name)->toBe('Standard')
        ->and($method->shippingAddress->cc)->toBe('NL')
        ->and($method->shippingAddress->postalCode)->toBe('2132WT')
        ->and($method->shippingAddress->isBusiness)->toBeFalse()
        ->and($method->hasDeliveryOptions)->toBeTrue();
});

it('marks a shipping address with a company as a business', function () {
    $method = cartRepository(Checkout::context(['company' => 'MyParcel']))->get(null)->shippingMethod;

    expect($method->shippingAddress->isBusiness)->toBeTrue();
});

it('gives only the country before the consumer has an address', function () {
    $address = cartRepository(Checkout::context(['withAddress' => false, 'countryIso' => 'be']))->get(null)->shippingMethod->shippingAddress;

    expect($address->cc)->toBe('BE')
        ->and($address->postalCode)->toBeNull();
});

it('maps a given shopware cart without loading the session cart', function () {
    $cartService = new FixedCartService();

    $cart = cartRepository(Checkout::context(), $cartService)->get(Checkout::cart(['lines' => []]));

    expect($cartService->contexts)->toBe([])
        ->and($cart->lines)->toHaveCount(0);
});

it('rejects any other input', function () {
    expect(fn () => cartRepository(Checkout::context())->get('some-id'))
        ->toThrow(InvalidArgumentException::class, 'string given');
});

it('fails clearly when no storefront entry point set the context', function () {
    expect(fn () => cartRepository()->get(null))
        ->toThrow(LogicException::class, 'no sales channel context');
});

it('maps a cart again on every call, also when its total stays the same', function () {
    $repository = cartRepository(Checkout::context());

    // Two versions of a cart with the same total, for example after a product is
    // replaced by one with the same price.
    $version = static function (int $quantity): Cart {
        $cart = Checkout::cart(['lines' => [[Entities::PRODUCT_ID, $quantity, 12.10, 2.10]]]);
        $cart->setPrice(new CartPrice(50, 60.5, 50, $cart->getPrice()->getCalculatedTaxes(), $cart->getPrice()->getTaxRules(), CartPrice::TAX_STATE_GROSS));

        return $cart;
    };

    expect($repository->get($version(2))->lines->first()->quantity)->toBe(2)
        ->and($repository->get($version(5))->lines->first()->quantity)->toBe(5);
});
