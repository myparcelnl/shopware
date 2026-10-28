<?php

declare(strict_types=1);

use MyParcel\Shopware\Pdk\Order\Mapper\AddressMapper;
use MyParcel\Shopware\Pdk\Order\Mapper\MoneyConverter;
use MyParcel\Shopware\Pdk\Order\Mapper\PdkOrderMapper;
use MyParcel\Shopware\Pdk\Order\Mapper\StreetSplitter;
use MyParcel\Shopware\Pdk\Order\Repository\PdkOrderNoteRepository;
use MyParcel\Shopware\Pdk\Order\Repository\PdkOrderRepository;
use MyParcel\Shopware\Pdk\Product\Mapper\PdkProductMapper;
use MyParcel\Shopware\Tests\Support\Fixtures;
use MyParcel\Shopware\Tests\Support\InMemoryOrderStorage;
use MyParcel\Shopware\Tests\Support\TestPdk;
use MyParcelNL\Pdk\App\Order\Repository\AbstractPdkOrderRepository;
use MyParcelNL\Pdk\Base\Exception\ModelNotFoundException;
use MyParcelNL\Pdk\Base\Service\CurrencyService;
use MyParcelNL\Pdk\Base\Service\WeightService;
use MyParcelNL\Pdk\Fulfilment\Collection\OrderNoteCollection;
use MyParcelNL\Pdk\Shipment\Collection\ShipmentCollection;
use MyParcelNL\Pdk\Storage\MemoryCacheStorage;

const NL_ORDER_ID = '0190a1b2c3d4e5f60718293a4b5c6d01';

beforeEach(fn () => TestPdk::boot());

function orderRepository(InMemoryOrderStorage $storage): PdkOrderRepository
{
    $currency = new CurrencyService();

    return new PdkOrderRepository(
        new MemoryCacheStorage(),
        $storage,
        new PdkOrderMapper(new AddressMapper(new StreetSplitter()), new MoneyConverter($currency), new PdkProductMapper($currency, new WeightService()))
    );
}

function storageWithNlOrder(): InMemoryOrderStorage
{
    $storage                         = new InMemoryOrderStorage();
    $storage->snapshots[NL_ORDER_ID] = Fixtures::snapshot('order-nl');

    return $storage;
}

it('is a pdk order repository', function () {
    expect(orderRepository(new InMemoryOrderStorage()))->toBeInstanceOf(AbstractPdkOrderRepository::class);
});

it('finds an order by id, by array and by model, and reads it once per request', function () {
    $storage    = storageWithNlOrder();
    $repository = orderRepository($storage);

    $order = $repository->find(NL_ORDER_ID);

    expect($order->externalIdentifier)->toBe(NL_ORDER_ID)
        ->and($repository->get(['externalIdentifier' => NL_ORDER_ID])->referenceIdentifier)->toBe('10001')
        ->and($repository->get($order)->externalIdentifier)->toBe(NL_ORDER_ID)
        ->and($storage->snapshotReads)->toBe(1);
});

it('returns null for an unknown or malformed id, and get throws', function () {
    $repository = orderRepository(new InMemoryOrderStorage());

    expect($repository->find('not-an-order'))->toBeNull()
        ->and(fn () => $repository->get('not-an-order'))->toThrow(ModelNotFoundException::class)
        ->and(fn () => $repository->findOrFail('not-an-order'))->toThrow(ModelNotFoundException::class);
});

it('gets many orders and leaves unknown ids out', function () {
    $orders = orderRepository(storageWithNlOrder())->getMany([NL_ORDER_ID, 'unknown']);

    expect($orders)->toHaveCount(1)
        ->and($orders->first()->externalIdentifier)->toBe(NL_ORDER_ID);
});

it('finds an order by its api identifier', function () {
    $storage                                  = storageWithNlOrder();
    $storage->apiIdentifiers['api-uuid-0001'] = NL_ORDER_ID;

    expect(orderRepository($storage)->getByApiIdentifier('api-uuid-0001')->externalIdentifier)->toBe(NL_ORDER_ID)
        ->and(orderRepository($storage)->getByApiIdentifier('unknown'))->toBeNull();
});

it('stores the order data and the shipments as a list', function () {
    $storage    = storageWithNlOrder();
    $repository = orderRepository($storage);
    $order      = $repository->find(NL_ORDER_ID);

    $order->exported = false;
    $repository->update($order);

    expect($storage->orderData[NL_ORDER_ID])->toMatchArray(['apiIdentifier' => 'api-uuid-0001', 'exported' => false])
        ->and(array_is_list($storage->shipments[NL_ORDER_ID]))->toBeTrue()
        ->and(array_column($storage->shipments[NL_ORDER_ID], 'id'))->toBe([101]);
});

it('merges stored shipments with the incoming ones by id and keeps new ones without an id', function () {
    $storage                         = storageWithNlOrder();
    $storage->shipments[NL_ORDER_ID] = [['id' => 101, 'carrier' => 'POSTNL'], ['id' => 102, 'carrier' => 'POSTNL']];
    $repository                      = orderRepository($storage);
    $order                           = $repository->find(NL_ORDER_ID);

    $order->shipments = new ShipmentCollection([
        ['id' => 102, 'carrier' => 'POSTNL', 'barcode' => '3STEST0000002'],
        ['id' => null, 'carrier' => 'POSTNL'],
    ]);
    $repository->update($order);

    $stored = $storage->shipments[NL_ORDER_ID];

    // toStorableArray() skips null values, so the shipment without an id has no id key.
    expect($stored)->toHaveCount(3)
        ->and(array_column($stored, 'id'))->toBe([101, 102])
        ->and($stored[2])->not->toHaveKey('id')
        ->and($stored[1]['barcode'])->toBe('3STEST0000002');
});

it('returns the updated order from the cache afterwards', function () {
    $storage    = storageWithNlOrder();
    $repository = orderRepository($storage);
    $order      = $repository->find(NL_ORDER_ID);

    $order->exported = false;
    $repository->update($order);

    expect($repository->find(NL_ORDER_ID)->exported)->toBeFalse()
        ->and($storage->snapshotReads)->toBe(1);
});

it('keeps the api identifiers that the pdk sets on the order notes when it stores them', function () {
    $storedNote                                  = ['externalIdentifier' => 'n1', 'orderIdentifier' => NL_ORDER_ID, 'author' => 'webshop', 'note' => 'Ingepakt'];
    $storage                                     = storageWithNlOrder();
    $storage->snapshots[NL_ORDER_ID]['notes']    = [$storedNote];
    $storage->notes[NL_ORDER_ID]                 = [$storedNote];
    $storage->customerComments[NL_ORDER_ID]      = 'Graag bij de buren afgeven';
    $noteRepository                              = new PdkOrderNoteRepository(new MemoryCacheStorage(), $storage);
    $order                                       = orderRepository($storage)->find(NL_ORDER_ID);

    // What PostOrderNotesAction does after it posted the notes to the API.
    $order->notes->addApiIdentifiers(new OrderNoteCollection([['uuid' => 'api-note-0'], ['uuid' => 'api-note-1']]));
    $noteRepository->updateMany($order->notes);

    expect($storage->notes[NL_ORDER_ID])->toHaveCount(1)
        ->and($storage->notes[NL_ORDER_ID][0])->toMatchArray(['externalIdentifier' => 'n1', 'apiIdentifier' => 'api-note-1']);
});
