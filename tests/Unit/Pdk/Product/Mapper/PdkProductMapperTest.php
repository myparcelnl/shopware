<?php

declare(strict_types=1);

use MyParcel\Shopware\Pdk\Product\Mapper\PdkProductMapper;
use MyParcel\Shopware\Tests\Support\Entities;
use MyParcel\Shopware\Tests\Support\TestPdk;
use MyParcelNL\Pdk\App\Order\Model\PdkProduct;
use MyParcelNL\Pdk\Base\Service\CurrencyService;
use MyParcelNL\Pdk\Base\Service\WeightService;
use MyParcelNL\Pdk\Types\Service\TriStateService;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Content\Product\ProductEntity;

beforeEach(fn () => TestPdk::boot());

/**
 * Maps a product with its own custom fields, and those of its parent, as the own
 * values.
 */
function mapProduct(ProductEntity $product, float $euroFactor = 1.0): PdkProduct
{
    $own = [$product->getId() => $product->getCustomFields() ?? []];

    if (null !== $product->getParent()) {
        $own[$product->getParent()->getId()] = $product->getParent()->getCustomFields() ?? [];
    }

    return (new PdkProductMapper(new CurrencyService(), new WeightService()))->map($product, $own, $euroFactor);
}

it('maps identity, price, weight in grams and dimensions in millimeters', function () {
    $product = mapProduct(Entities::product());

    expect($product->externalIdentifier)->toBe(Entities::PRODUCT_ID)
        ->and($product->sku)->toBe('SW-1')
        ->and($product->ean)->toBe('8710000000001')
        ->and($product->name)->toBe('Testproduct')
        ->and($product->price->amount)->toBe(1210)
        ->and($product->price->currency)->toBe('EUR')
        ->and($product->weight)->toBe(250)
        ->and($product->length)->toBe(205)
        ->and($product->width)->toBe(100)
        ->and($product->height)->toBe(50)
        ->and($product->isDeliverable)->toBeTrue();
});

it('rounds the dimensions to whole millimeters', function () {
    expect(mapProduct(Entities::product(['width' => 12.6, 'height' => 12.4]))->toArray())
        ->toMatchArray(['width' => 13, 'height' => 12]);
});

dataset('weights', [
    '2.007 kg, one gram too many with ceil(2.007 * 1000)' => [2.007, 2007],
    'half a gram'                                         => [0.0005, 1],
    'a quarter kilogram'                                  => [0.25, 250],
]);

it('converts the weight to grams without a floating point gram too many', function (float $kilograms, int $grams) {
    expect(mapProduct(Entities::product(['weight' => $kilograms]))->weight)->toBe($grams);
})->with('weights');

it('converts the price in the default currency to euro', function () {
    expect(mapProduct(Entities::product(['gross' => 12.10]), 0.9)->price->amount)->toBe(1089);
});

it('treats a digital product as not deliverable', function () {
    expect(mapProduct(Entities::product(['type' => ProductDefinition::TYPE_DIGITAL]))->isDeliverable)->toBeFalse();
});

it('uses zero for missing weight, price and dimensions', function () {
    $product = mapProduct(Entities::product(['weight' => null, 'gross' => null, 'length' => null]));

    expect($product->weight)->toBe(0)
        ->and($product->price->amount)->toBe(0)
        ->and($product->length)->toBe(0);
});

it('reads the settings from the own custom fields of the product', function () {
    $product = mapProduct(Entities::product(['customFields' => ['myparcel_package_type' => 'mailbox']]));

    expect($product->settings->packageType)->toBe('mailbox')
        ->and($product->settings->fitInMailbox)->toBe(TriStateService::INHERIT);
});

it('ignores custom field values that the product only has through inheritance', function () {
    $product = (new PdkProductMapper(new CurrencyService(), new WeightService()))->map(
        Entities::product(['customFields' => ['myparcel_package_type' => 'mailbox']]),
        [Entities::PRODUCT_ID => []],
        1.0
    );

    expect($product->settings->packageType)->toBe(TriStateService::INHERIT);
});

it('lets a variant inherit settings from its parent', function () {
    $parentId = Entities::id();
    $variant  = mapProduct(Entities::product([
        'id'           => Entities::id(),
        'parentId'     => $parentId,
        'customFields' => ['myparcel_fit_in_mailbox' => 0],
        'parent'       => Entities::product([
            'id'           => $parentId,
            'customFields' => ['myparcel_package_type' => 'mailbox', 'myparcel_fit_in_mailbox' => 3],
        ]),
    ]));

    expect($variant->parent->externalIdentifier)->toBe($parentId)
        ->and($variant->mergedSettings->packageType)->toBe('mailbox')
        ->and($variant->mergedSettings->fitInMailbox)->toBe(0);
});
