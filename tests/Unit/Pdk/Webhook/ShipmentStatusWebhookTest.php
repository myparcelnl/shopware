<?php

declare(strict_types=1);

use MyParcel\Shopware\Pdk\Cron\SynchronousCronService;
use MyParcel\Shopware\Pdk\Currency\EuroCurrency;
use MyParcel\Shopware\Pdk\Order\Mapper\AddressMapper;
use MyParcel\Shopware\Pdk\Order\Mapper\MoneyConverter;
use MyParcel\Shopware\Pdk\Order\Mapper\PdkOrderMapper;
use MyParcel\Shopware\Pdk\Order\Mapper\StreetSplitter;
use MyParcel\Shopware\Pdk\Order\OrderStatusService;
use MyParcel\Shopware\Pdk\Order\Repository\PdkOrderRepository;
use MyParcel\Shopware\Pdk\Order\Writer\OrderDataWriter;
use MyParcel\Shopware\Pdk\Product\Mapper\PdkProductMapper;
use MyParcel\Shopware\Pdk\Product\Repository\PdkProductRepository;
use MyParcel\Shopware\Pdk\Product\Writer\ProductSettingsWriter;
use MyParcel\Shopware\Tests\Support\Entities;
use MyParcel\Shopware\Tests\Support\FakeEntityRepository;
use MyParcel\Shopware\Tests\Support\FixedLocaleResolver;
use MyParcel\Shopware\Tests\Support\RecordingConnection;
use MyParcel\Shopware\Tests\Support\RecordingOrderStateTransitioner;
use MyParcel\Shopware\Tests\Support\TestPdk;
use MyParcelNL\Pdk\App\Order\Contract\OrderStatusServiceInterface;
use MyParcelNL\Pdk\App\Order\Contract\PdkOrderRepositoryInterface;
use MyParcelNL\Pdk\App\Webhook\Contract\PdkWebhookManagerInterface;
use MyParcelNL\Pdk\App\Webhook\Contract\PdkWebhooksRepositoryInterface;
use MyParcelNL\Pdk\Base\Contract\CronServiceInterface;
use MyParcelNL\Pdk\Base\Service\CurrencyService;
use MyParcelNL\Pdk\Base\Service\WeightService;
use MyParcelNL\Pdk\Facade\Pdk;
use MyParcelNL\Pdk\Settings\Model\OrderSettings;
use MyParcelNL\Pdk\Storage\MemoryCacheStorage;
use MyParcelNL\Pdk\Tests\Api\Response\ExampleGetShipmentsResponse;
use MyParcelNL\Pdk\Tests\Bootstrap\MockApi;
use MyParcelNL\Pdk\Webhook\Collection\WebhookSubscriptionCollection;
use MyParcelNL\Pdk\Webhook\Model\WebhookSubscription;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use function MyParcelNL\Pdk\Tests\factory as pdkFactory;

/*
 * The path of a shipment status webhook through the plugin: the PDK webhook
 * manager, the synchronous cron service, the PDK order repository and the order
 * status service. The PDK API and the Shopware state machine are doubles.
 */

const STATUS_WEBHOOK_URL = 'https://shop.test/api/myparcel/webhook/0123456789abcdef';

/**
 * Boots the PDK with the plugin's order repository and order status service.
 * The shipment rows go to $shipments, the transitions to $transitioner.
 */
function bootStatusWebhookPdk(FakeEntityRepository $shipments, RecordingOrderStateTransitioner $transitioner): void
{
    $currency = new CurrencyService();
    $euro     = new EuroCurrency(FakeEntityRepository::with(Entities::currency('EUR', 1.0)));

    TestPdk::boot([
        CronServiceInterface::class        => new SynchronousCronService(),
        PdkOrderRepositoryInterface::class => new PdkOrderRepository(
            new MemoryCacheStorage(),
            FakeEntityRepository::with(Entities::order()),
            $euro,
            new PdkProductRepository(
                new MemoryCacheStorage(),
                FakeEntityRepository::with(Entities::product()),
                $euro,
                new PdkProductMapper($currency, new WeightService()),
                new ProductSettingsWriter(FakeEntityRepository::with())
            ),
            new PdkOrderMapper(new AddressMapper(new StreetSplitter()), new MoneyConverter($currency)),
            new OrderDataWriter(FakeEntityRepository::with(), $shipments, new RecordingConnection())
        ),
        OrderStatusServiceInterface::class => new OrderStatusService(
            FakeEntityRepository::with(),
            FakeEntityRepository::with(),
            new RequestStack(),
            new FixedLocaleResolver(null),
            $transitioner
        ),
    ]);

    pdkFactory(OrderSettings::class)
        ->withStatusOnLabelCreate('in_progress')
        ->withStatusWhenDelivered('completed')
        ->store();
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
    $shipments    = FakeEntityRepository::with();
    $transitioner = new RecordingOrderStateTransitioner();
    bootStatusWebhookPdk($shipments, $transitioner);

    sendStatusWebhook(3);

    $stored = array_column($shipments->upserts, 'data');

    expect(array_column($stored, 'id'))->toBe([101])
        ->and($stored[0])->toMatchArray(['status' => 3, 'barcode' => '3STEST0000101']);
});

it('moves the order to the state that the settings give for the shipment status', function (int $apiStatus, string $state) {
    $transitioner = new RecordingOrderStateTransitioner();
    bootStatusWebhookPdk(FakeEntityRepository::with(), $transitioner);

    sendStatusWebhook($apiStatus);

    expect($transitioner->calls)->toBe([['orderIds' => [Entities::ORDER_ID], 'technicalName' => $state]]);
})->with([
    'label created' => [2, 'in_progress'],
    'delivered'     => [7, 'completed'],
]);
