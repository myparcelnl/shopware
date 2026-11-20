<?php

declare(strict_types=1);

use MyParcel\Shopware\Checkout\CartDeliveryOptions;
use MyParcel\Shopware\Checkout\SalesChannelContextHolder;
use MyParcel\Shopware\Controller\DeliveryOptionsController;
use MyParcel\Shopware\Tests\Support\Checkout;
use MyParcel\Shopware\Tests\Support\FixedCartService;
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
    $cart   = new Cart(Checkout::TOKEN);
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
