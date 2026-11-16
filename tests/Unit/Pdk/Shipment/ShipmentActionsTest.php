<?php

declare(strict_types=1);

use MyParcel\Shopware\Tests\Support\Entities;
use MyParcel\Shopware\Tests\Support\PluginOrderPdk;
use MyParcelNL\Pdk\App\Api\Backend\PdkBackendActions;
use MyParcelNL\Pdk\Facade\Actions;
use MyParcelNL\Pdk\Facade\Pdk;
use MyParcelNL\Pdk\Shipment\Model\Shipment;
use MyParcelNL\Pdk\Tests\Api\Response\ExampleGetShipmentsResponse;
use MyParcelNL\Pdk\Tests\Api\Response\ExamplePostIdsResponse;
use MyParcelNL\Pdk\Tests\Bootstrap\MockApi;
use MyParcelNL\Pdk\Tests\Bootstrap\MockApiService;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Uuid\Uuid;

/*
 * The delete and return actions of the PDK on the shipments that the plugin
 * stores.
 */

/**
 * The test order with the stored shipments 101 and 102.
 *
 * @param  list<string> $trackingCodes the tracking codes of the delivery
 */
function orderWithTwoShipments(array $trackingCodes = []): OrderEntity
{
    return Entities::order([
        'primaryDelivery' => Entities::delivery(Entities::address(), Entities::price(4.95, 1, 0.86), Entities::DELIVERY_ID, $trackingCodes),
        'shipments'       => [
            ['id' => 101, 'carrier' => 'POSTNL', 'orderId' => Entities::ORDER_ID, 'referenceIdentifier' => Entities::ORDER_ID, 'barcode' => '3STEST0000101'],
            ['id' => 102, 'carrier' => 'POSTNL', 'orderId' => Entities::ORDER_ID, 'referenceIdentifier' => Entities::ORDER_ID, 'barcode' => '3STEST0000102'],
        ],
    ]);
}

it('deletes the row of a deleted shipment and keeps the other rows', function () {
    $pdk = PluginOrderPdk::boot(orderWithTwoShipments());

    Actions::execute(PdkBackendActions::DELETE_SHIPMENTS, ['orderIds' => [Entities::ORDER_ID], 'shipmentIds' => [101]]);

    expect(array_column($pdk->storedShipments(), 'id'))->toBe([102])
        ->and($pdk->shipments->deletes)->toBe([['id' => Uuid::fromStringToHex(Entities::ORDER_ID . '.101')]]);
});

it('removes the barcode of a deleted shipment from the tracking codes', function () {
    $pdk = PluginOrderPdk::boot(orderWithTwoShipments(['3STEST0000101', '3STEST0000102']));

    Actions::execute(PdkBackendActions::DELETE_SHIPMENTS, ['orderIds' => [Entities::ORDER_ID], 'shipmentIds' => [101]]);

    expect($pdk->orders->updates[0]['deliveries'][0]['trackingCodes'] ?? null)->toBe(['3STEST0000102']);
});

// @TODO INT-2274: expect 201 in the stored shipments when the PDK keeps a new
// return shipment with its order. Until then the PDK drops it, because a
// shipment from the API has no orderId.
it('creates the return shipment and keeps the stored shipments of the order', function () {
    $pdk = PluginOrderPdk::boot(orderWithTwoShipments());

    MockApi::enqueue(
        new ExamplePostIdsResponse([['id' => 201]]),
        new ExampleGetShipmentsResponse([
            array_replace(ExampleGetShipmentsResponse::DEFAULT_SHIPMENT_DATA, [
                'id'                   => 201,
                'parent_id'            => 102,
                'shipment_type'        => Shipment::SHIPMENT_TYPE_RETURN,
                'reference_identifier' => Entities::ORDER_ID,
            ]),
        ])
    );

    Actions::execute(PdkBackendActions::EXPORT_RETURN, ['orderIds' => [Entities::ORDER_ID]]);

    expect(Pdk::get(MockApiService::class)->ensureLastRequest()->getUri()->getPath())->toBe('API/shipments/201')
        ->and(array_column($pdk->storedShipments(), 'id'))->toBe([101, 102]);
});
