<?php

declare(strict_types=1);

use MyParcel\Shopware\Pdk\Currency\EuroCurrency;
use MyParcel\Shopware\Pdk\Order\Mapper\AddressMapper;
use MyParcel\Shopware\Pdk\Order\Mapper\MoneyConverter;
use MyParcel\Shopware\Pdk\Order\Mapper\PdkOrderMapper;
use MyParcel\Shopware\Pdk\Order\Mapper\StreetSplitter;
use MyParcel\Shopware\Pdk\Order\Repository\PdkOrderNoteRepository;
use MyParcel\Shopware\Pdk\Order\Repository\PdkOrderRepository;
use MyParcel\Shopware\Pdk\Order\Writer\OrderDataWriter;
use MyParcel\Shopware\Pdk\Product\Mapper\PdkProductMapper;
use MyParcel\Shopware\Pdk\Product\Repository\PdkProductRepository;
use MyParcel\Shopware\Pdk\Product\Writer\ProductSettingsWriter;
use MyParcel\Shopware\Tests\Support\Entities;
use MyParcel\Shopware\Tests\Support\FakeEntityRepository;
use MyParcel\Shopware\Tests\Support\RecordingConnection;
use MyParcel\Shopware\Tests\Support\TestPdk;
use MyParcelNL\Pdk\App\Order\Repository\AbstractPdkOrderRepository;
use MyParcelNL\Pdk\Base\Exception\ModelNotFoundException;
use MyParcelNL\Pdk\Base\Service\CurrencyService;
use MyParcelNL\Pdk\Base\Service\WeightService;
use MyParcelNL\Pdk\Fulfilment\Collection\OrderNoteCollection;
use MyParcelNL\Pdk\Shipment\Collection\ShipmentCollection;
use MyParcelNL\Pdk\Storage\MemoryCacheStorage;
use Shopware\Core\Checkout\Order\OrderCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;

beforeEach(fn () => TestPdk::boot());

function euroCurrency(float $factor = 1.0): EuroCurrency
{
    return new EuroCurrency(FakeEntityRepository::with(Entities::currency('EUR', $factor)));
}

/**
 * A repository on these orders. The writes go to $orderData and $shipments.
 */
function orderRepository(
    FakeEntityRepository $orders,
    ?FakeEntityRepository $orderData = null,
    ?FakeEntityRepository $shipments = null,
    float $euroFactor = 1.0
): PdkOrderRepository {
    $currency = new CurrencyService();

    return new PdkOrderRepository(
        new MemoryCacheStorage(),
        $orders,
        euroCurrency($euroFactor),
        new PdkProductRepository(
            new MemoryCacheStorage(),
            FakeEntityRepository::with(Entities::product(['customFields' => ['myparcel_package_type' => 'mailbox']])),
            euroCurrency($euroFactor),
            new PdkProductMapper($currency, new WeightService()),
            new ProductSettingsWriter(FakeEntityRepository::with())
        ),
        new PdkOrderMapper(new AddressMapper(new StreetSplitter()), new MoneyConverter($currency)),
        new OrderDataWriter($orderData ?? FakeEntityRepository::with(), $shipments ?? FakeEntityRepository::with(), new RecordingConnection())
    );
}

it('is a pdk order repository', function () {
    expect(orderRepository(FakeEntityRepository::with()))->toBeInstanceOf(AbstractPdkOrderRepository::class);
});

it('finds an order by id, by array and by model, and reads it once per request', function () {
    $orders     = FakeEntityRepository::with(Entities::order());
    $repository = orderRepository($orders);

    $order = $repository->find(Entities::ORDER_ID);

    expect($order->externalIdentifier)->toBe(Entities::ORDER_ID)
        ->and($repository->get(['externalIdentifier' => Entities::ORDER_ID])->referenceIdentifier)->toBe('10001')
        ->and($repository->get($order)->externalIdentifier)->toBe(Entities::ORDER_ID)
        ->and($orders->criteria)->toHaveCount(1);
});

it('reads the products of the order lines through the product repository', function () {
    $line = orderRepository(FakeEntityRepository::with(Entities::order()))->find(Entities::ORDER_ID)->lines->first();

    expect($line->product->sku)->toBe('SW-1')
        ->and($line->product->weight)->toBe(250)
        ->and($line->product->settings->packageType)->toBe('mailbox');
});

it('converts the amounts with the factor of the euro currency', function () {
    $repository = orderRepository(FakeEntityRepository::with(Entities::order()), null, null, 0.9);

    // 12.10 * 0.9 = 10.89 euro. A factor of 1.0 gives 1210.
    expect($repository->find(Entities::ORDER_ID)->lines->first()->priceAfterVat)->toBe(1089);
});

it('returns null for an unknown or malformed id, and get throws', function () {
    $repository = orderRepository(FakeEntityRepository::with());

    expect($repository->find(Entities::id()))->toBeNull()
        ->and($repository->find('not-an-order'))->toBeNull()
        ->and(fn () => $repository->get('not-an-order'))->toThrow(ModelNotFoundException::class)
        ->and(fn () => $repository->findOrFail('not-an-order'))->toThrow(ModelNotFoundException::class);
});

it('gets many orders in one read and leaves unknown ids out', function () {
    $orders = FakeEntityRepository::with(Entities::order());
    $found  = orderRepository($orders)->getMany([Entities::ORDER_ID, Entities::id()]);

    expect($found)->toHaveCount(1)
        ->and($found->first()->externalIdentifier)->toBe(Entities::ORDER_ID)
        ->and($orders->criteria)->toHaveCount(1);
});

it('finds an order by its api identifier', function () {
    $order  = Entities::order();
    $orders = new FakeEntityRepository(static function (Criteria $criteria) use ($order): OrderCollection {
        foreach ($criteria->getFilters() as $filter) {
            if ($filter instanceof EqualsFilter && 'api-uuid-0001' !== $filter->getValue()) {
                return new OrderCollection();
            }
        }

        return new OrderCollection([$order]);
    });

    expect(orderRepository($orders)->getByApiIdentifier('api-uuid-0001')->externalIdentifier)->toBe(Entities::ORDER_ID)
        ->and($orders->criteria[0]->getFilters())->toEqual([new EqualsFilter('myparcelOrderData.apiIdentifier', 'api-uuid-0001')])
        ->and(orderRepository($orders)->getByApiIdentifier('unknown'))->toBeNull();
});

it('stores the order data and the shipments', function () {
    $orderData  = FakeEntityRepository::with();
    $shipments  = FakeEntityRepository::with();
    $repository = orderRepository(FakeEntityRepository::with(Entities::order()), $orderData, $shipments);
    $order      = $repository->find(Entities::ORDER_ID);

    $order->exported = false;
    $repository->update($order);

    expect($orderData->upserts[0]['data'])->toMatchArray(['apiIdentifier' => 'api-uuid-0001', 'exported' => false])
        ->and(array_column(array_column($shipments->upserts, 'data'), 'id'))->toBe([101]);
});

it('merges stored shipments with the incoming ones by id and keeps new ones without an id', function () {
    $shipments  = FakeEntityRepository::with();
    $orders     = FakeEntityRepository::with(Entities::order([
        'shipments' => [['id' => 101, 'carrier' => 'POSTNL'], ['id' => 102, 'carrier' => 'POSTNL']],
    ]));
    $repository = orderRepository($orders, null, $shipments);
    $order      = $repository->find(Entities::ORDER_ID);

    $order->shipments = new ShipmentCollection([
        ['id' => 102, 'carrier' => 'POSTNL', 'barcode' => '3STEST0000002'],
        ['id' => null, 'carrier' => 'POSTNL'],
    ]);
    $updated = $repository->update($order);

    // toStorableArray() skips null values, so the shipment without an id has no id key.
    $stored = array_column($shipments->upserts, 'data');

    expect($stored)->toHaveCount(3)
        ->and(array_column($stored, 'id'))->toBe([101, 102])
        ->and($stored[2])->not->toHaveKey('id')
        ->and($stored[1]['barcode'])->toBe('3STEST0000002')
        ->and($updated->shipments)->toHaveCount(3);
});

it('returns the updated order from the cache afterwards', function () {
    $orders     = FakeEntityRepository::with(Entities::order());
    $repository = orderRepository($orders);
    $order      = $repository->find(Entities::ORDER_ID);

    $order->exported = false;
    $repository->update($order);

    // One read for find(), one for the stored shipments in update().
    expect($repository->find(Entities::ORDER_ID)->exported)->toBeFalse()
        ->and($orders->criteria)->toHaveCount(2);
});

it('keeps the api identifiers that the pdk sets on the order notes when it stores them', function () {
    $storedNote     = ['externalIdentifier' => 'n1', 'orderIdentifier' => Entities::ORDER_ID, 'author' => 'webshop', 'note' => 'Ingepakt'];
    $orders         = FakeEntityRepository::with(Entities::order(['notes' => [$storedNote]]));
    $orderData      = FakeEntityRepository::with();
    $noteRepository = new PdkOrderNoteRepository(
        new MemoryCacheStorage(),
        $orders,
        new OrderDataWriter($orderData, FakeEntityRepository::with(), new RecordingConnection())
    );
    $order          = orderRepository($orders)->find(Entities::ORDER_ID);

    // What PostOrderNotesAction does after it posted the notes to the API.
    $order->notes->addApiIdentifiers(new OrderNoteCollection([['uuid' => 'api-note-0'], ['uuid' => 'api-note-1']]));
    $noteRepository->updateMany($order->notes);

    $saved = $orderData->upserts[0]['notes'];

    expect($saved)->toHaveCount(1)
        ->and($saved[0])->toMatchArray(['externalIdentifier' => 'n1', 'apiIdentifier' => 'api-note-1']);
});
