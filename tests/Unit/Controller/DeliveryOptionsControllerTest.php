<?php

declare(strict_types=1);

use MyParcel\Shopware\Checkout\CartDeliveryOptions;
use MyParcel\Shopware\Checkout\SalesChannelContextHolder;
use MyParcel\Shopware\Controller\DeliveryOptionsController;
use MyParcel\Shopware\Tests\Support\Checkout;
use MyParcel\Shopware\Tests\Support\FixedCartService;
use MyParcel\Shopware\Tests\Support\InstantiationProbe;
use MyParcel\Shopware\Tests\Support\PdkServices;
use MyParcel\Shopware\Tests\Support\RecordingLogger;
use MyParcel\Shopware\Tests\Support\TestPdk;
use Psr\Log\LogLevel;
use Shopware\Core\Checkout\Cart\Cart;
use Symfony\Component\HttpFoundation\Request;

beforeEach(fn () => TestPdk::boot());

function deliveryOptionsController(FixedCartService $cartService, ?RecordingLogger $logger = null): DeliveryOptionsController
{
    $logger ??= new RecordingLogger();
    $holder   = new SalesChannelContextHolder();

    return new DeliveryOptionsController(PdkServices::initializer($logger, $holder), $holder, $cartService, $logger);
}

function jsonRequest(string $content): Request
{
    return new Request([], [], [], [], [], ['CONTENT_TYPE' => 'application/json'], $content);
}

it('stores the normalized choice on the cart and recalculates it', function () {
    $cart        = new Cart(Checkout::TOKEN);
    $cartService = new FixedCartService($cart);

    $response = deliveryOptionsController($cartService)
        ->store(jsonRequest('{"deliveryOptions":{"carrier":"POSTNL","deliveryType":"evening","shipmentOptions":{"signature":true}}}'), Checkout::context());

    $stored = CartDeliveryOptions::read($cart);

    expect($response->getStatusCode())->toBe(204)
        ->and($stored['carrier'])->toBe('POSTNL')
        ->and($stored['deliveryType'])->toBe('evening')
        ->and($stored['shipmentOptions']['signature'])->toBe(1)
        ->and($cartService->recalculated)->toBe([$cart]);
});

it('answers an error when the recalculation forgot the choice', function () {
    $cart        = new Cart(Checkout::TOKEN);
    $cartService = new FixedCartService($cart, static fn (Cart $cart) => CartDeliveryOptions::forget($cart));

    $response = deliveryOptionsController($cartService)
        ->store(jsonRequest('{"deliveryOptions":{"carrier":"POSTNL","deliveryType":"evening"}}'), Checkout::context());

    expect($response->getStatusCode())->toBe(400)
        ->and($cartService->recalculated)->toBe([$cart]);
});

it('refuses a body that is not json or has no delivery options', function (string $content) {
    $cartService = new FixedCartService();

    $response = deliveryOptionsController($cartService)
        ->store(jsonRequest($content), Checkout::context());

    expect($response->getStatusCode())->toBe(400)
        ->and($cartService->recalculated)->toBe([]);
})->with(['not json' => ['{'], 'no key' => ['{"other":1}'], 'empty' => ['{"deliveryOptions":{}}'], 'string' => ['{"deliveryOptions":"x"}'], 'list' => ['[1]']]);

it('refuses a carrier that the account does not have, and logs it', function () {
    $logger = new RecordingLogger();
    $cartService = new FixedCartService();

    $response = deliveryOptionsController($cartService, $logger)
        ->store(jsonRequest('{"deliveryOptions":{"carrier":"NOT_A_CARRIER"}}'), Checkout::context());

    expect($response->getStatusCode())->toBe(400)
        ->and($cartService->recalculated)->toBe([])
        ->and($logger->records[0]['level'])->toBe(LogLevel::WARNING);
});

/**
 * Stores the choice on a new cart and returns the stored choice, or null.
 *
 * @param  array<string, mixed> $deliveryOptions
 *
 * @return array{0: int, 1: null|array<string, mixed>}
 */
function storeChoice(array $deliveryOptions): array
{
    $cart = new Cart(Checkout::TOKEN);

    $response = deliveryOptionsController(new FixedCartService($cart))
        ->store(jsonRequest(json_encode(['deliveryOptions' => $deliveryOptions], JSON_THROW_ON_ERROR)), Checkout::context());

    return [$response->getStatusCode(), CartDeliveryOptions::read($cart)];
}

const PICKUP_LOCATION = ['locationCode' => '123', 'locationName' => 'Testpunt', 'street' => 'Teststraat', 'number' => '1', 'postalCode' => '2132JE', 'city' => 'Hoofddorp', 'cc' => 'NL', 'latitude' => 52.3, 'distance' => 120];

it('refuses a class name in a class-cast attribute before the pdk creates the class', function (array $deliveryOptions) {
    InstantiationProbe::$created = 0;

    [$status, $stored] = storeChoice(['carrier' => 'POSTNL'] + $deliveryOptions);

    expect($status)->toBe(400)
        ->and($stored)->toBeNull()
        ->and(InstantiationProbe::$created)->toBe(0);
})->with([
    'shipmentOptions' => [['shipmentOptions' => InstantiationProbe::class]],
    'pickupLocation'  => [['deliveryType' => 'pickup', 'pickupLocation' => InstantiationProbe::class]],
    'date'            => [['date' => InstantiationProbe::class]],
]);

it('stores only the keys that the widget sends', function () {
    [$status, $stored] = storeChoice(['carrier' => 'POSTNL', 'deliveryType' => 'standard', 'labelAmount' => 10, 'contractId' => '99', 'isPickup' => false]);

    expect($status)->toBe(204)
        ->and($stored['labelAmount'] ?? 1)->toBe(1)
        ->and($stored['contractId'] ?? null)->toBeNull();
});

it('refuses what the widget does not send', function (array $deliveryOptions) {
    [$status, $stored] = storeChoice(['carrier' => 'POSTNL'] + $deliveryOptions);

    expect($status)->toBe(400)
        ->and($stored)->toBeNull();
})->with([
    'pickup without a location'      => [['deliveryType' => 'pickup']],
    'pickup with a nested location'  => [['deliveryType' => 'pickup', 'pickupLocation' => ['street' => ['x']]]],
    'pickup without a location code' => [['deliveryType' => 'pickup', 'pickupLocation' => ['a' => 1]]],
    'pickup with an empty code'      => [['deliveryType' => 'pickup', 'pickupLocation' => ['locationCode' => '']]],
    'pickup by its id, no location'  => [['deliveryType' => '4']],
    'home delivery, nested location' => [['deliveryType' => 'evening', 'pickupLocation' => ['street' => ['x']]]],
    'an unknown shipment option'     => [['shipmentOptions' => ['ageCheck' => true]]],
    'a shipment option not a bool'   => [['shipmentOptions' => ['signature' => 'yes']]],
    'a date that is not a date'      => [['date' => 'no date']],
    'a delivery type not a string'   => [['deliveryType' => ['evening']]],
]);

it('stores a pickup with its location', function () {
    [$status, $stored] = storeChoice(['carrier' => 'POSTNL', 'deliveryType' => 'pickup', 'isPickup' => true, 'pickupLocation' => PICKUP_LOCATION]);

    expect($status)->toBe(204)
        ->and($stored['deliveryType'])->toBe('pickup')
        ->and($stored['pickupLocation']['locationCode'])->toBe('123');
});

it('reads a pickup by its id as a pickup', function () {
    [$status, $stored] = storeChoice(['carrier' => 'POSTNL', 'deliveryType' => '4', 'pickupLocation' => PICKUP_LOCATION]);

    expect($status)->toBe(204)
        ->and($stored['deliveryType'])->toBe('pickup')
        ->and($stored['pickupLocation']['locationCode'])->toBe('123');
});

it('drops a pickup location from a home delivery', function () {
    [$status, $stored] = storeChoice(['carrier' => 'POSTNL', 'deliveryType' => 'evening', 'pickupLocation' => PICKUP_LOCATION]);

    expect($status)->toBe(204)
        ->and($stored['pickupLocation'] ?? null)->toBeNull();
});

it('stores the full output of the widget', function () {
    [$status, $stored] = storeChoice([
        'carrier'         => 'POSTNL',
        'date'            => '2026-11-25T00:00:00.000Z',
        'deliveryType'    => 'evening',
        'isPickup'        => false,
        'packageType'     => 'package',
        'shipmentOptions' => ['signature' => true, 'onlyRecipient' => false, 'priorityDelivery' => false, 'sameDayDelivery' => false],
    ]);

    expect($status)->toBe(204)
        ->and($stored['deliveryType'])->toBe('evening')
        ->and($stored['shipmentOptions']['signature'])->toBe(1);
});
