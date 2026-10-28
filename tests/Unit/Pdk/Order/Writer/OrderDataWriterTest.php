<?php

declare(strict_types=1);

use MyParcel\Shopware\Pdk\Order\Writer\OrderDataWriter;
use MyParcel\Shopware\Tests\Support\Entities;
use MyParcel\Shopware\Tests\Support\FakeEntityRepository;
use MyParcel\Shopware\Tests\Support\RecordingConnection;
use MyParcel\Shopware\Tests\Support\TestPdk;
use MyParcelNL\Pdk\Shipment\Collection\ShipmentCollection;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Uuid\Uuid;

beforeEach(fn () => TestPdk::boot());

function shipmentRowId(int $shipmentId): string
{
    return Uuid::fromStringToHex(Entities::ORDER_ID . '.' . $shipmentId);
}

it('upserts the order data with a fixed id and the api identifier', function () {
    $orderData = FakeEntityRepository::with();
    $writer    = new OrderDataWriter($orderData, FakeEntityRepository::with(), new RecordingConnection());

    $writer->saveOrderData(Entities::ORDER_ID, ['apiIdentifier' => 'api-uuid-0001', 'exported' => true]);
    $writer->saveNotes(Entities::ORDER_ID, [['externalIdentifier' => 'n1', 'note' => 'Ingepakt']]);

    $id = Uuid::fromStringToHex('myparcel_order_data.' . Entities::ORDER_ID);

    expect($orderData->upserts)->toBe([
        [
            'id'             => $id,
            'orderId'        => Entities::ORDER_ID,
            'orderVersionId' => Defaults::LIVE_VERSION,
            'apiIdentifier'  => 'api-uuid-0001',
            'data'           => ['apiIdentifier' => 'api-uuid-0001', 'exported' => true],
        ],
        [
            'id'             => $id,
            'orderId'        => Entities::ORDER_ID,
            'orderVersionId' => Defaults::LIVE_VERSION,
            'notes'          => [['externalIdentifier' => 'n1', 'note' => 'Ingepakt']],
        ],
    ]);
});

it('keeps the row id of a shipment on every save, in one transaction', function () {
    $shipments  = FakeEntityRepository::with();
    $connection = new RecordingConnection();
    $writer     = new OrderDataWriter(FakeEntityRepository::with(), $shipments, $connection);

    $writer->saveShipments(Entities::ORDER_ID, new ShipmentCollection([['id' => 101, 'carrier' => 'POSTNL']]));
    $writer->saveShipments(Entities::ORDER_ID, new ShipmentCollection([['id' => 101, 'carrier' => 'POSTNL', 'barcode' => '3STEST0000001']]));

    expect(array_column($shipments->upserts, 'id'))->toBe([shipmentRowId(101), shipmentRowId(101)])
        ->and($shipments->upserts[1]['data'])->toMatchArray(['id' => 101, 'barcode' => '3STEST0000001'])
        ->and($shipments->upserts[1]['position'])->toBe(0)
        ->and($shipments->deletes)->toBe([])
        ->and($connection->transactions)->toBe(2);
});

it('deletes only the row of a shipment that the pdk marked deleted', function () {
    $shipments = FakeEntityRepository::with(
        Entities::shipment(Entities::ORDER_ID, 0, ['id' => 101], shipmentRowId(101)),
        Entities::shipment(Entities::ORDER_ID, 1, ['id' => 102], shipmentRowId(102))
    );
    $writer    = new OrderDataWriter(FakeEntityRepository::with(), $shipments, new RecordingConnection());

    $writer->saveShipments(Entities::ORDER_ID, new ShipmentCollection([
        ['id' => 101, 'deleted' => '2026-10-28 12:00:00'],
        ['id' => 102],
    ]));

    expect($shipments->deletes)->toBe([['id' => shipmentRowId(101)]])
        ->and(array_column($shipments->upserts, 'id'))->toBe([shipmentRowId(102)]);
});

it('keeps a stored shipment that the saved list does not have', function () {
    $shipments = FakeEntityRepository::with(
        Entities::shipment(Entities::ORDER_ID, 0, ['id' => 101], shipmentRowId(101)),
        Entities::shipment(Entities::ORDER_ID, 1, ['id' => 102], shipmentRowId(102))
    );
    $writer    = new OrderDataWriter(FakeEntityRepository::with(), $shipments, new RecordingConnection());

    $writer->saveShipments(Entities::ORDER_ID, new ShipmentCollection([['id' => 102]]));

    expect($shipments->deletes)->toBe([])
        ->and(array_column($shipments->upserts, 'id'))->toBe([shipmentRowId(102)]);
});

it('replaces the rows of shipments without an id on each save', function () {
    $newRowId  = static fn (int $position) => Uuid::fromStringToHex(Entities::ORDER_ID . '.new.' . $position);
    $shipments = FakeEntityRepository::with(
        Entities::shipment(Entities::ORDER_ID, 1, ['carrier' => 'POSTNL'], $newRowId(1)),
        Entities::shipment(Entities::ORDER_ID, 2, ['carrier' => 'POSTNL'], $newRowId(2))
    );
    $writer    = new OrderDataWriter(FakeEntityRepository::with(), $shipments, new RecordingConnection());

    $writer->saveShipments(Entities::ORDER_ID, new ShipmentCollection([['id' => 101], ['carrier' => 'POSTNL']]));

    expect(array_column($shipments->upserts, 'id'))->toBe([shipmentRowId(101), $newRowId(1)])
        ->and($shipments->deletes)->toBe([['id' => $newRowId(2)]]);
});

it('replaces the row of a shipment that got an id with the row of its id', function () {
    $newRowId  = Uuid::fromStringToHex(Entities::ORDER_ID . '.new.0');
    $shipments = FakeEntityRepository::with(
        Entities::shipment(Entities::ORDER_ID, 0, ['id' => null, 'carrier' => 'POSTNL'], $newRowId)
    );
    $writer    = new OrderDataWriter(FakeEntityRepository::with(), $shipments, new RecordingConnection());

    $writer->saveShipments(Entities::ORDER_ID, new ShipmentCollection([['id' => 105, 'carrier' => 'POSTNL']]));

    expect($shipments->deletes)->toBe([['id' => $newRowId]])
        ->and(array_column($shipments->upserts, 'id'))->toBe([shipmentRowId(105)]);
});
