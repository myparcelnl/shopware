<?php

declare(strict_types=1);

use MyParcel\Shopware\Pdk\Product\Mapper\PdkProductMapper;
use MyParcel\Shopware\Tests\Support\TestPdk;
use MyParcelNL\Pdk\Base\Service\CurrencyService;
use MyParcelNL\Pdk\Base\Service\WeightService;
use MyParcelNL\Pdk\Types\Service\TriStateService;

beforeEach(fn () => TestPdk::boot());

function productMapper(): PdkProductMapper
{
    return new PdkProductMapper(new CurrencyService(), new WeightService());
}

function productSnapshot(array $overrides = []): array
{
    return array_replace([
        'id'            => '0190a1b2c3d4e5f60718293a4b5c0001',
        'parentId'      => null,
        'productNumber' => 'SW-1',
        'ean'           => '8710000000001',
        'name'          => 'Testproduct',
        'priceGross'    => 12.10,
        'weight'        => 0.25,
        'width'         => 100.0,
        'height'        => 50.0,
        'length'        => 205.0,
        'type'          => 'physical',
        'customFields'  => [],
        'parent'        => null,
    ], $overrides);
}

it('maps identity, price, weight in grams and dimensions in centimeters', function () {
    $product = productMapper()->fromSnapshot(productSnapshot());

    expect($product->externalIdentifier)->toBe('0190a1b2c3d4e5f60718293a4b5c0001')
        ->and($product->sku)->toBe('SW-1')
        ->and($product->ean)->toBe('8710000000001')
        ->and($product->name)->toBe('Testproduct')
        ->and($product->price->amount)->toBe(1210)
        ->and($product->price->currency)->toBe('EUR')
        ->and($product->weight)->toBe(250)
        ->and($product->length)->toBe(21)
        ->and($product->width)->toBe(10)
        ->and($product->height)->toBe(5)
        ->and($product->isDeliverable)->toBeTrue();
});

it('treats a digital product as not deliverable', function () {
    expect(productMapper()->fromSnapshot(productSnapshot(['type' => 'digital']))->isDeliverable)->toBeFalse();
});

it('uses zero for missing weight, price and dimensions', function () {
    $product = productMapper()->fromSnapshot(productSnapshot(['weight' => null, 'priceGross' => null, 'length' => null]));

    expect($product->weight)->toBe(0)
        ->and($product->price->amount)->toBe(0)
        ->and($product->length)->toBe(0);
});

it('reads the settings from the custom fields', function () {
    $product = productMapper()->fromSnapshot(productSnapshot(['customFields' => ['myparcel_package_type' => 'mailbox']]));

    expect($product->settings->packageType)->toBe('mailbox')
        ->and($product->settings->fitInMailbox)->toBe(TriStateService::INHERIT);
});

it('lets a variant inherit settings from its parent', function () {
    $variant = productMapper()->fromSnapshot(productSnapshot([
        'id'           => 'variant',
        'parentId'     => 'parent',
        'customFields' => ['myparcel_fit_in_mailbox' => 0],
        'parent'       => productSnapshot([
            'id'           => 'parent',
            'customFields' => ['myparcel_package_type' => 'mailbox', 'myparcel_fit_in_mailbox' => 3],
        ]),
    ]));

    expect($variant->parent->externalIdentifier)->toBe('parent')
        ->and($variant->mergedSettings->packageType)->toBe('mailbox')
        ->and($variant->mergedSettings->fitInMailbox)->toBe(0);
});
