<?php

declare(strict_types=1);

use MyParcel\Shopware\Tests\Support\Entities;
use MyParcel\Shopware\Tests\Support\PluginOrderPdk;
use MyParcelNL\Pdk\App\Api\Backend\PdkBackendActions;
use MyParcelNL\Pdk\Facade\Actions;
use MyParcelNL\Pdk\Facade\Pdk;
use MyParcelNL\Pdk\Settings\Model\LabelSettings;
use MyParcelNL\Pdk\Settings\Model\OrderSettings;
use MyParcelNL\Pdk\Tests\Api\Response\ExampleGetShipmentLabelsPdfResponse;
use MyParcelNL\Pdk\Tests\Bootstrap\MockApi;
use MyParcelNL\Pdk\Tests\Bootstrap\MockApiService;
use function MyParcelNL\Pdk\Tests\factory as pdkFactory;

/*
 * The print action of the PDK on the orders and shipments that the plugin
 * stores. The PDK reads the label format and positions from the label
 * settings; these tests show that the plugin passes the stored shipments to it.
 */

/**
 * Boots the PDK for the test order with the stored shipments 101 and 102.
 */
function bootPrintPdk(): PluginOrderPdk
{
    $pdk = PluginOrderPdk::boot(Entities::order(['shipments' => [
        ['id' => 101, 'carrier' => 'POSTNL', 'orderId' => Entities::ORDER_ID, 'barcode' => '3STEST0000101'],
        ['id' => 102, 'carrier' => 'POSTNL', 'orderId' => Entities::ORDER_ID, 'barcode' => '3STEST0000102'],
    ]]));

    pdkFactory(OrderSettings::class)
        ->withStatusOnLabelCreate('in_progress')
        ->store();

    return $pdk;
}

/**
 * Prints the labels of the test order and returns the query of the label
 * request to the API.
 *
 * @param  array<string, mixed> $parameters extra parameters of the print action
 */
function printTestOrder(array $parameters = []): string
{
    MockApi::enqueue(new ExampleGetShipmentLabelsPdfResponse());

    Actions::execute(PdkBackendActions::PRINT_SHIPMENTS, array_replace(['orderIds' => [Entities::ORDER_ID]], $parameters));

    return Pdk::get(MockApiService::class)->ensureLastRequest()->getUri()->getQuery();
}

it('prints with the label format and positions of the settings', function (array $settings, string $query) {
    bootPrintPdk();
    pdkFactory(LabelSettings::class)->with($settings)->store();

    expect(printTestOrder())->toBe($query);
})->with([
    'default settings' => [[], 'format=A4&positions=1%3B2%3B3%3B4'],
    'a4, positions 2 and 4' => [
        [LabelSettings::FORMAT => LabelSettings::FORMAT_A4, LabelSettings::POSITION => [2, 4]],
        'format=A4&positions=2%3B4',
    ],
    'a6' => [[LabelSettings::FORMAT => LabelSettings::FORMAT_A6], 'format=A6&positions=1%3B2%3B3%3B4'],
]);

it('prints with the format and positions of the request when the merchant chose them', function () {
    bootPrintPdk();
    pdkFactory(LabelSettings::class)->withFormat(LabelSettings::FORMAT_A4)->store();

    $query = printTestOrder([LabelSettings::FORMAT => LabelSettings::FORMAT_A6, LabelSettings::POSITION => '3,4']);

    expect($query)->toBe('format=A6&positions=3%3B4');
});

it('prints all stored shipments of the order, or the shipments that the request names', function (array $parameters, string $path) {
    bootPrintPdk();

    printTestOrder($parameters);

    expect(Pdk::get(MockApiService::class)->ensureLastRequest()->getUri()->getPath())->toBe($path);
})->with([
    'no shipment ids' => [[], 'API/shipment_labels/101;102'],
    'shipment ids'    => [['shipmentIds' => [102]], 'API/shipment_labels/102'],
]);

it('moves the order to the state for a created label', function () {
    $pdk = bootPrintPdk();

    printTestOrder();

    expect($pdk->transitioner->calls)->toBe([['orderIds' => [Entities::ORDER_ID], 'technicalName' => 'in_progress']]);
});
