<?php

declare(strict_types=1);

use MyParcel\Shopware\Pdk\Cron\SynchronousCronService;
use MyParcel\Shopware\Tests\Support\Entities;
use MyParcel\Shopware\Tests\Support\PluginOrderPdk;
use MyParcelNL\Pdk\App\Webhook\Contract\PdkWebhookManagerInterface;
use MyParcelNL\Pdk\App\Webhook\Contract\PdkWebhooksRepositoryInterface;
use MyParcelNL\Pdk\Base\Contract\CronServiceInterface;
use MyParcelNL\Pdk\Facade\Pdk;
use MyParcelNL\Pdk\Settings\Model\OrderSettings;
use MyParcelNL\Pdk\Tests\Api\Response\ExampleGetShipmentsResponse;
use MyParcelNL\Pdk\Tests\Bootstrap\MockApi;
use MyParcelNL\Pdk\Webhook\Collection\WebhookSubscriptionCollection;
use MyParcelNL\Pdk\Webhook\Model\WebhookSubscription;
use Symfony\Component\HttpFoundation\Request;
use function MyParcelNL\Pdk\Tests\factory as pdkFactory;

/*
 * The path of a shipment status webhook through the plugin: the PDK webhook
 * manager, the synchronous cron service, the PDK order repository and the order
 * status service.
 */

const STATUS_WEBHOOK_URL = 'https://shop.test/api/myparcel/webhook/0123456789abcdef';

/**
 * Boots the PDK for the test order, with the synchronous cron service and the
 * order states for a created label and a delivered shipment.
 */
function bootStatusWebhookPdk(): PluginOrderPdk
{
    $pdk = PluginOrderPdk::boot(null, [CronServiceInterface::class => new SynchronousCronService()]);

    pdkFactory(OrderSettings::class)
        ->withStatusOnLabelCreate('in_progress')
        ->withStatusWhenDelivered('completed')
        ->store();

    return $pdk;
}

/**
 * Sends a shipment status webhook for shipment 101 of the test order. The API
 * answers with that shipment in $apiStatus.
 */
function sendStatusWebhook(int $apiStatus): void
{
    /** @var PdkWebhooksRepositoryInterface $webhooks */
    $webhooks = Pdk::get(PdkWebhooksRepositoryInterface::class);
    $webhooks->storeHashedUrl(STATUS_WEBHOOK_URL);
    $webhooks->store(new WebhookSubscriptionCollection([[
        'hook' => WebhookSubscription::SHIPMENT_STATUS_CHANGE,
        'url'  => STATUS_WEBHOOK_URL,
    ]]));

    MockApi::enqueue(new ExampleGetShipmentsResponse([
        array_replace(ExampleGetShipmentsResponse::DEFAULT_SHIPMENT_DATA, [
            'id'                   => 101,
            'status'               => $apiStatus,
            'barcode'              => '3STEST0000101',
            'reference_identifier' => Entities::ORDER_ID,
        ]),
    ]));

    $hook = [
        'event'                         => WebhookSubscription::SHIPMENT_STATUS_CHANGE,
        'shipment_id'                   => 101,
        'shipment_reference_identifier' => Entities::ORDER_ID,
        'status'                        => $apiStatus,
    ];

    Pdk::get(PdkWebhookManagerInterface::class)->call(Request::create(
        STATUS_WEBHOOK_URL,
        Request::METHOD_POST,
        [],
        [],
        [],
        ['HTTP_X_MYPARCEL_HOOK' => WebhookSubscription::SHIPMENT_STATUS_CHANGE],
        (string) json_encode(['data' => ['hooks' => [$hook]]])
    ));
}

it('stores the shipment that a status webhook names, in the same request', function () {
    $pdk = bootStatusWebhookPdk();

    sendStatusWebhook(3);

    $stored = $pdk->storedShipments();

    expect(array_column($stored, 'id'))->toBe([101])
        ->and($stored[0])->toMatchArray(['status' => 3, 'barcode' => '3STEST0000101']);
});

it('moves the order to the state that the settings give for the shipment status', function (int $apiStatus, string $state) {
    $pdk = bootStatusWebhookPdk();

    sendStatusWebhook($apiStatus);

    expect($pdk->transitioner->calls)->toBe([['orderIds' => [Entities::ORDER_ID], 'technicalName' => $state]]);
})->with([
    'label created' => [2, 'in_progress'],
    'delivered'     => [7, 'completed'],
]);
