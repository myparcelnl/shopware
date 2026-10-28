<?php

declare(strict_types=1);

use MyParcel\Shopware\Pdk\Order\Mapper\MoneyConverter;
use MyParcel\Shopware\Tests\Support\Entities;
use MyParcelNL\Pdk\Base\Service\CurrencyService;
use Shopware\Core\Checkout\Cart\Price\Struct\CartPrice;

function moneyConverter(): MoneyConverter
{
    return new MoneyConverter(new CurrencyService());
}

it('splits a gross unit price into price and vat', function () {
    expect(moneyConverter()->toUnitCents(Entities::price(12.10, 2, 4.20), 1.0, 1.0, CartPrice::TAX_STATE_GROSS))
        ->toBe(['price' => 1000, 'vat' => 210, 'priceAfterVat' => 1210]);
});

it('adds vat to a net unit price', function () {
    expect(moneyConverter()->toUnitCents(Entities::price(10.00, 2, 4.20), 1.0, 1.0, CartPrice::TAX_STATE_NET))
        ->toBe(['price' => 1000, 'vat' => 210, 'priceAfterVat' => 1210]);
});

it('has no vat when the order is tax free', function () {
    expect(moneyConverter()->toUnitCents(Entities::price(10.00, 1, 2.10), 1.0, 1.0, CartPrice::TAX_STATE_FREE))
        ->toBe(['price' => 1000, 'vat' => 0, 'priceAfterVat' => 1000]);
});

it('converts to euro with the currency factor of the order', function () {
    expect(moneyConverter()->toUnitCents(Entities::price(10.50, 1, 1.75), 0.875, 1.0, CartPrice::TAX_STATE_GROSS))
        ->toBe(['price' => 1000, 'vat' => 200, 'priceAfterVat' => 1200]);
});

it('converts to euro with the euro factor when the default currency is not euro', function () {
    // Default currency USD; 1 USD = 0.8 GBP (the order currency) = 0.9 EUR.
    expect(moneyConverter()->toUnitCents(Entities::price(8.00, 1, 1.39), 0.8, 0.9, CartPrice::TAX_STATE_GROSS))
        ->toBe(['price' => 744, 'vat' => 156, 'priceAfterVat' => 900]);
});

it('treats an unknown tax status as gross', function () {
    expect(moneyConverter()->toUnitCents(Entities::price(12.10, 1, 2.10), 1.0, 1.0, 'something-else'))
        ->toBe(['price' => 1000, 'vat' => 210, 'priceAfterVat' => 1210]);
});

it('does not divide by a zero quantity or a zero currency factor', function () {
    expect(moneyConverter()->toUnitCents(Entities::price(12.10, 0, 2.10), 0.0, 1.0, CartPrice::TAX_STATE_GROSS))
        ->toBe(['price' => 1000, 'vat' => 210, 'priceAfterVat' => 1210]);
});

it('converts a single amount to cents', function () {
    expect(moneyConverter()->toCents(10.50, 0.875, 1.0))->toBe(1200)
        ->and(moneyConverter()->toCents(0.333, 1.0, 1.0))->toBe(33)
        ->and(moneyConverter()->toCents(10.00, 1.0, 0.9))->toBe(900);
});
