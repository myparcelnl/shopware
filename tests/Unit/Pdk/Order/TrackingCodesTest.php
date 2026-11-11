<?php

declare(strict_types=1);

use MyParcel\Shopware\Pdk\Order\TrackingCodes;
use MyParcel\Shopware\Tests\Support\TestPdk;
use MyParcelNL\Pdk\Shipment\Collection\ShipmentCollection;

beforeEach(fn () => TestPdk::boot());

/**
 * @param  list<string>               $existing
 * @param  list<array<string, mixed>> $shipments
 *
 * @return list<string>
 */
function mergeTrackingCodes(array $existing, array $shipments): array
{
    return TrackingCodes::merge($existing, new ShipmentCollection($shipments));
}

it('adds the barcode of a shipment', function () {
    expect(mergeTrackingCodes([], [['id' => 1, 'barcode' => '3SA']]))->toBe(['3SA']);
});

it('keeps the tracking codes that the merchant entered, first', function () {
    expect(mergeTrackingCodes(['OWN-1'], [['id' => 1, 'barcode' => '3SA']]))->toBe(['OWN-1', '3SA']);
});

it('does not add a barcode that is already there', function () {
    expect(mergeTrackingCodes(['3SA'], [['id' => 1, 'barcode' => '3SA'], ['id' => 2, 'barcode' => '3SA']]))->toBe(['3SA']);
});

it('removes the barcode of a deleted shipment', function () {
    expect(mergeTrackingCodes(
        ['OWN-1', '3SA', '3SB'],
        [['id' => 1, 'barcode' => '3SA', 'deleted' => '2026-11-10 10:00:00'], ['id' => 2, 'barcode' => '3SB']]
    ))->toBe(['OWN-1', '3SB']);
});

it('leaves out return shipments and shipments without a barcode', function () {
    expect(mergeTrackingCodes([], [
        ['id' => 1, 'barcode' => '3SRETURN', 'isReturn' => true],
        ['id' => 2],
        ['id' => 3, 'barcode' => ''],
    ]))->toBe([]);
});
