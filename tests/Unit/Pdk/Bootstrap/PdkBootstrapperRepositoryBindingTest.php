<?php

declare(strict_types=1);

use MyParcel\Shopware\Pdk\Currency\EuroCurrency;
use MyParcel\Shopware\Pdk\Frontend\ViewService;
use MyParcel\Shopware\Pdk\Order\Repository\PdkOrderNoteRepository;
use MyParcel\Shopware\Pdk\Order\Repository\PdkOrderRepository;
use MyParcel\Shopware\Pdk\Order\Writer\OrderDataWriter;
use MyParcel\Shopware\Pdk\PdkBootstrapper;
use MyParcel\Shopware\Pdk\Product\Repository\PdkProductRepository;
use MyParcel\Shopware\Pdk\Product\Writer\ProductSettingsWriter;
use MyParcel\Shopware\Pdk\ShopwareServices;
use MyParcel\Shopware\Tests\Support\FakeEntityRepository;
use MyParcel\Shopware\Tests\Support\FixedLocaleResolver;
use MyParcel\Shopware\Tests\Support\FixedOrderStatusService;
use MyParcel\Shopware\Tests\Support\FixedUrlResolver;
use MyParcel\Shopware\Tests\Support\InMemoryConfigStorage;
use MyParcel\Shopware\Tests\Support\RecordingConnection;
use MyParcel\Shopware\Tests\Support\TestPdk;
use MyParcelNL\Pdk\App\Order\Contract\PdkOrderNoteRepositoryInterface;
use MyParcelNL\Pdk\App\Order\Contract\PdkOrderRepositoryInterface;
use MyParcelNL\Pdk\App\Order\Contract\PdkProductRepositoryInterface;
use MyParcelNL\Pdk\Facade\Pdk;
use Symfony\Component\HttpFoundation\RequestStack;

/*
 * The repository bindings in PdkBootstrapper name the constructor parameter that
 * gets the Shopware repository, because php-di cannot tell the EntityRepository
 * instances apart by type. These tests fail when a parameter is renamed.
 */

/**
 * The definitions of PdkBootstrapper::getAdditionalConfig() that the given
 * entries need.
 *
 * @param  list<string> $keys
 *
 * @return array<string, mixed>
 */
function bootstrapperDefinitions(array $keys): array
{
    $definitions = (new ReflectionMethod(PdkBootstrapper::class, 'getAdditionalConfig'))
        ->invoke(new PdkBootstrapper(), 'myparcel', 'MyParcel', '0.0.0-test', dirname(__DIR__, 4), 'https://shop.test');

    return array_intersect_key($definitions, array_flip($keys));
}

beforeEach(function () {
    $this->orders   = FakeEntityRepository::with();
    $this->products = FakeEntityRepository::with();

    PdkBootstrapper::setServices(new ShopwareServices(
        new InMemoryConfigStorage(),
        new FixedLocaleResolver(null),
        new FixedUrlResolver([]),
        $this->orders,
        $this->products,
        new EuroCurrency(FakeEntityRepository::with()),
        new OrderDataWriter(FakeEntityRepository::with(), FakeEntityRepository::with(), new RecordingConnection()),
        new ProductSettingsWriter(FakeEntityRepository::with()),
        new ViewService(new RequestStack()),
        new FixedOrderStatusService()
    ));

    TestPdk::boot(bootstrapperDefinitions([
        PdkOrderRepositoryInterface::class,
        PdkOrderNoteRepositoryInterface::class,
        PdkProductRepositoryInterface::class,
        PdkProductRepository::class,
        EuroCurrency::class,
        OrderDataWriter::class,
        ProductSettingsWriter::class,
    ]));
});

it('gives each pdk repository the shopware repository of its entity', function (string $contract, string $class, string $property, string $expected) {
    $repository = Pdk::get($contract);

    expect($repository)->toBeInstanceOf($class)
        ->and((new ReflectionProperty($class, $property))->getValue($repository))->toBe($this->{$expected});
})->with([
    'order'      => [PdkOrderRepositoryInterface::class, PdkOrderRepository::class, 'orderRepository', 'orders'],
    'order note' => [PdkOrderNoteRepositoryInterface::class, PdkOrderNoteRepository::class, 'orderRepository', 'orders'],
    'product'    => [PdkProductRepositoryInterface::class, PdkProductRepository::class, 'productRepository', 'products'],
]);

it('gives the product repository class the same instance as its pdk contract', function () {
    expect(Pdk::get(PdkProductRepository::class))->toBe(Pdk::get(PdkProductRepositoryInterface::class));
});
