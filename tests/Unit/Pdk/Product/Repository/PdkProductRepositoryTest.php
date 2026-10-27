<?php

declare(strict_types=1);

use MyParcel\Shopware\Pdk\Product\Mapper\PdkProductMapper;
use MyParcel\Shopware\Pdk\Product\Repository\PdkProductRepository;
use MyParcel\Shopware\Tests\Support\InMemoryProductStorage;
use MyParcel\Shopware\Tests\Support\TestPdk;
use MyParcelNL\Pdk\App\Order\Model\PdkProduct;
use MyParcelNL\Pdk\App\Order\Repository\AbstractPdkPdkProductRepository;
use MyParcelNL\Pdk\Base\Service\CurrencyService;
use MyParcelNL\Pdk\Base\Service\WeightService;
use MyParcelNL\Pdk\Settings\Model\ProductSettings;
use MyParcelNL\Pdk\Storage\MemoryCacheStorage;

beforeEach(fn () => TestPdk::boot());

function productRepository(InMemoryProductStorage $storage): PdkProductRepository
{
    return new PdkProductRepository(new MemoryCacheStorage(), $storage, new PdkProductMapper(new CurrencyService(), new WeightService()));
}

function storageWithProduct(): InMemoryProductStorage
{
    $storage                  = new InMemoryProductStorage();
    $storage->snapshots['p1'] = [
        'id' => 'p1', 'parentId' => null, 'productNumber' => 'SW-1', 'ean' => null, 'name' => 'Testproduct',
        'priceGross' => 12.10, 'weight' => 0.25, 'width' => null, 'height' => null, 'length' => null,
        'type' => 'physical', 'customFields' => ['myparcel_package_type' => 'mailbox'], 'parent' => null,
    ];

    return $storage;
}

it('is a pdk product repository', function () {
    expect(productRepository(new InMemoryProductStorage()))->toBeInstanceOf(AbstractPdkPdkProductRepository::class);
});

it('gets a product and its settings', function () {
    $repository = productRepository(storageWithProduct());

    expect($repository->getProduct('p1')->sku)->toBe('SW-1')
        ->and($repository->getProductSettings('p1')->packageType)->toBe('mailbox');
});

it('gives a bare product for an unknown id', function () {
    expect(productRepository(new InMemoryProductStorage())->getProduct('unknown')->externalIdentifier)->toBe('unknown');
});

it('gets many products and leaves unknown ids out', function () {
    expect(productRepository(storageWithProduct())->getProducts(['p1', 'unknown']))->toHaveCount(1)
        ->and(productRepository(storageWithProduct())->getProducts())->toHaveCount(0);
});

it('stores the settings as custom fields and returns them afterwards', function () {
    $storage    = storageWithProduct();
    $repository = productRepository($storage);
    $product    = new PdkProduct(['externalIdentifier' => 'p1', 'settings' => [ProductSettings::CUSTOMS_CODE => '61091000']]);

    $repository->update($product);

    expect($storage->savedSettings['p1']['myparcel_customs_code'])->toBe('61091000')
        ->and($storage->savedSettings['p1']['myparcel_package_type'])->toBeNull()
        ->and($repository->getProductSettings('p1')->customsCode)->toBe('61091000');
});
