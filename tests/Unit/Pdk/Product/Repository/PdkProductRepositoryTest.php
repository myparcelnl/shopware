<?php

declare(strict_types=1);

use MyParcel\Shopware\Pdk\Currency\EuroCurrency;
use MyParcel\Shopware\Pdk\Product\Mapper\PdkProductMapper;
use MyParcel\Shopware\Pdk\Product\Repository\PdkProductRepository;
use MyParcel\Shopware\Pdk\Product\Writer\ProductSettingsWriter;
use MyParcel\Shopware\Tests\Support\Entities;
use MyParcel\Shopware\Tests\Support\FakeEntityRepository;
use MyParcel\Shopware\Tests\Support\TestPdk;
use MyParcelNL\Pdk\App\Order\Model\PdkProduct;
use MyParcelNL\Pdk\App\Order\Repository\AbstractPdkPdkProductRepository;
use MyParcelNL\Pdk\Base\Service\CurrencyService;
use MyParcelNL\Pdk\Base\Service\WeightService;
use MyParcelNL\Pdk\Settings\Model\ProductSettings;
use MyParcelNL\Pdk\Storage\MemoryCacheStorage;
use MyParcelNL\Pdk\Types\Service\TriStateService;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;

beforeEach(fn () => TestPdk::boot());

function productRepository(FakeEntityRepository $products, ?FakeEntityRepository $writes = null, float $euroFactor = 1.0): PdkProductRepository
{
    return new PdkProductRepository(
        new MemoryCacheStorage(),
        $products,
        new EuroCurrency(FakeEntityRepository::with(Entities::currency('EUR', $euroFactor))),
        new PdkProductMapper(new CurrencyService(), new WeightService()),
        new ProductSettingsWriter($writes ?? FakeEntityRepository::with())
    );
}

it('is a pdk product repository', function () {
    expect(productRepository(FakeEntityRepository::with()))->toBeInstanceOf(AbstractPdkPdkProductRepository::class);
});

it('gets a product and its settings, and reads it once per request', function () {
    $products   = FakeEntityRepository::with(Entities::product(['customFields' => ['myparcel_package_type' => 'mailbox']]));
    $repository = productRepository($products);

    expect($repository->getProduct(Entities::PRODUCT_ID)->sku)->toBe('SW-1')
        ->and($repository->getProductSettings(Entities::PRODUCT_ID)->packageType)->toBe('mailbox')
        ->and($products->criteria)->toHaveCount(2);
});

it('converts the price with the factor of the euro currency', function () {
    $products = FakeEntityRepository::with(Entities::product(['gross' => 10.00]));

    // 10.00 * 0.9 = 9.00 euro. A factor of 1.0 gives 1000.
    expect(productRepository($products, null, 0.9)->getProduct(Entities::PRODUCT_ID)->price->amount)->toBe(900);
});

it('reads the product data with inheritance and the settings without', function () {
    $products = FakeEntityRepository::with(Entities::product());

    productRepository($products)->getProduct(Entities::PRODUCT_ID);

    expect($products->contexts[0]->considerInheritance())->toBeTrue()
        // Shopware throws ParentAssociationCanNotBeFetched for this association.
        ->and($products->criteria[0]->hasAssociation('parent'))->toBeFalse()
        ->and($products->contexts[1]->considerInheritance())->toBeFalse();
});

it('keeps settings that a variant only inherits out of its own settings', function () {
    $parent   = Entities::product(['id' => Entities::id(), 'customFields' => ['myparcel_package_type' => 'mailbox']]);
    $variant  = Entities::product(['id' => Entities::id(), 'parentId' => $parent->getId(), 'parent' => $parent]);
    // With inheritance, Shopware merges the custom fields of the parent into those
    // of the variant. It does not load the parent association.
    $inherited = Entities::product([
        'id'           => $variant->getId(),
        'parentId'     => $parent->getId(),
        'customFields' => ['myparcel_package_type' => 'mailbox'],
    ]);
    $products  = new FakeEntityRepository(static fn (Criteria $criteria, Context $context): EntityCollection => new EntityCollection(
        $context->considerInheritance() ? [$inherited, $parent] : [$variant, $parent]
    ));

    $product = productRepository($products)->getProduct($variant->getId());

    expect($product->settings->packageType)->toBe(TriStateService::INHERIT)
        ->and($product->mergedSettings->packageType)->toBe('mailbox');
});

it('gives a bare product for an unknown or malformed id', function () {
    $repository = productRepository(FakeEntityRepository::with());

    expect($repository->getProduct(Entities::id())->sku)->toBeNull()
        ->and($repository->getProduct('not-a-product')->externalIdentifier)->toBe('not-a-product');
});

it('gets many products and leaves unknown ids out', function () {
    $repository = productRepository(FakeEntityRepository::with(Entities::product()));

    expect($repository->getProducts([Entities::PRODUCT_ID, Entities::id()]))->toHaveCount(1)
        ->and($repository->getProducts())->toHaveCount(0);
});

it('stores the settings as custom fields and returns them afterwards', function () {
    $writes     = FakeEntityRepository::with();
    $repository = productRepository(FakeEntityRepository::with(Entities::product()), $writes);
    $product    = new PdkProduct(['externalIdentifier' => Entities::PRODUCT_ID, 'settings' => [ProductSettings::CUSTOMS_CODE => '61091000']]);

    $repository->update($product);

    expect($writes->updates[0]['customFields']['myparcel_customs_code'])->toBe('61091000')
        ->and($writes->updates[0]['customFields']['myparcel_package_type'])->toBeNull()
        ->and($repository->getProductSettings(Entities::PRODUCT_ID)->customsCode)->toBe('61091000');
});
