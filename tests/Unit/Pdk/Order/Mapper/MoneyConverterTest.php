<?php

declare(strict_types=1);

use MyParcel\Shopware\Pdk\Order\Mapper\MoneyConverter;
use MyParcelNL\Pdk\Base\Service\CurrencyService;

function moneyConverter(): MoneyConverter
{
    return new MoneyConverter(new CurrencyService());
}

function price(float $unitPrice, int $quantity, float ...$taxes): array
{
    return [
        'unitPrice' => $unitPrice,
        'quantity'  => $quantity,
        'taxes'     => array_map(static fn (float $tax): array => ['tax' => $tax, 'taxRate' => 21.0], $taxes),
    ];
}

it('splits a gross unit price into price and vat', function () {
    expect(moneyConverter()->toUnitCents(price(12.10, 2, 4.20), 1.0, 'gross'))
        ->toBe(['price' => 1000, 'vat' => 210, 'priceAfterVat' => 1210]);
});

it('adds vat to a net unit price', function () {
    expect(moneyConverter()->toUnitCents(price(10.00, 2, 4.20), 1.0, 'net'))
        ->toBe(['price' => 1000, 'vat' => 210, 'priceAfterVat' => 1210]);
});

it('has no vat when the order is tax free', function () {
    expect(moneyConverter()->toUnitCents(price(10.00, 1, 2.10), 1.0, 'tax-free'))
        ->toBe(['price' => 1000, 'vat' => 0, 'priceAfterVat' => 1000]);
});

it('converts to euro with the currency factor of the order', function () {
    expect(moneyConverter()->toUnitCents(price(10.50, 1, 1.75), 0.875, 'gross'))
        ->toBe(['price' => 1000, 'vat' => 200, 'priceAfterVat' => 1200]);
});

it('treats an unknown tax status as gross', function () {
    expect(moneyConverter()->toUnitCents(price(12.10, 1, 2.10), 1.0, 'something-else'))
        ->toBe(['price' => 1000, 'vat' => 210, 'priceAfterVat' => 1210]);
});

it('does not divide by a zero quantity or a zero currency factor', function () {
    expect(moneyConverter()->toUnitCents(price(12.10, 0, 2.10), 0.0, 'gross'))
        ->toBe(['price' => 1000, 'vat' => 210, 'priceAfterVat' => 1210]);
});

it('converts a single amount to cents', function () {
    expect(moneyConverter()->toCents(10.50, 0.875))->toBe(1200)
        ->and(moneyConverter()->toCents(0.333, 1.0))->toBe(33);
});
