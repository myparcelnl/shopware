<?php

declare(strict_types=1);

use MyParcel\Shopware\Checkout\SalesChannelContextHolder;
use MyParcel\Shopware\Pdk\Cart\Mapper\PdkCartMapper;
use MyParcel\Shopware\Pdk\Cart\Repository\PdkCartRepository;
use MyParcel\Shopware\Pdk\Currency\EuroCurrency;
use MyParcel\Shopware\Pdk\Order\Mapper\MoneyConverter;
use MyParcel\Shopware\Pdk\Product\Mapper\PdkProductMapper;
use MyParcel\Shopware\Pdk\Product\Repository\PdkProductRepository;
use MyParcel\Shopware\Pdk\Product\Writer\ProductSettingsWriter;
use MyParcel\Shopware\Tests\Support\Checkout;
use MyParcel\Shopware\Tests\Support\Entities;
use MyParcel\Shopware\Tests\Support\FakeEntityRepository;
use MyParcel\Shopware\Tests\Support\FixedCartLoader;
use MyParcel\Shopware\Tests\Support\TestPdk;
use MyParcelNL\Pdk\App\Cart\Repository\AbstractPdkCartRepository;
use MyParcelNL\Pdk\Base\Service\CurrencyService;
use MyParcelNL\Pdk\Base\Service\WeightService;
use MyParcelNL\Pdk\Storage\MemoryCacheStorage;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\Price\Struct\CartPrice;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

beforeEach(fn () => TestPdk::boot());

function cartRepository(
    ?SalesChannelContext $context = null,
    ?FixedCartLoader $loader = null,
    ?FakeEntityRepository $products = null
): PdkCartRepository {
    $holder = new SalesChannelContextHolder();

    if (null !== $context) {
        $holder->set($context);
    }

    $euroCurrency = new EuroCurrency(FakeEntityRepository::with(Entities::currency('EUR', 1.0)));

    return new PdkCartRepository(
        new MemoryCacheStorage(),
        $holder,
        $loader ?? new FixedCartLoader(Checkout::cart()),
        new PdkProductRepository(
            new MemoryCacheStorage(),
            $products ?? FakeEntityRepository::with(Entities::product()),
            $euroCurrency,
            new PdkProductMapper(new CurrencyService(), new WeightService()),
            new ProductSettingsWriter(FakeEntityRepository::with())
        ),
        $euroCurrency,
        new PdkCartMapper(new MoneyConverter(new CurrencyService()))
    );
}

it('is a pdk cart repository', function () {
    expect(cartRepository())->toBeInstanceOf(AbstractPdkCartRepository::class);
});

it('reads the cart of the current request for null, as the checkout context action asks', function () {
    $context = Checkout::context();
    $loader  = new FixedCartLoader(Checkout::cart());

    $cart = cartRepository($context, $loader)->get(null);

    expect($loader->contexts)->toBe([$context])
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
    $loader = new FixedCartLoader(Checkout::cart(['shippingCosts' => 5.00, 'taxStatus' => CartPrice::TAX_STATE_NET]));

    $cart = cartRepository(Checkout::context(['taxState' => CartPrice::TAX_STATE_NET]), $loader)->get(null);

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
    $loader = new FixedCartLoader();

    $cart = cartRepository(Checkout::context(), $loader)->get(Checkout::cart(['lines' => []]));

    expect($loader->contexts)->toBe([])
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

it('maps a cart that changes within the request again', function () {
    $repository = cartRepository(Checkout::context());
    $cart       = Checkout::cart();

    $first = $repository->get($cart);
    $cart->setPrice(new CartPrice(0, 99.0, 99.0, $cart->getPrice()->getCalculatedTaxes(), $cart->getPrice()->getTaxRules(), CartPrice::TAX_STATE_GROSS));

    expect($repository->get($cart))->not->toBe($first)
        ->and($repository->get(new Cart('other')))->not->toBe($first);
});
