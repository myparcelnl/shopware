<?php

declare(strict_types=1);

use MyParcel\Shopware\Checkout\CartDeliveryOptions;
use MyParcel\Shopware\Controller\DeliveryOptionsController;
use MyParcel\Shopware\Tests\Support\Checkout;
use MyParcel\Shopware\Tests\Support\FixedCartLoader;
use MyParcel\Shopware\Tests\Support\FixedDeliveryOptionsGateway;
use MyParcel\Shopware\Tests\Support\RecordingLogger;
use Psr\Log\LogLevel;
use Shopware\Core\Checkout\Cart\Cart;
use Symfony\Component\HttpFoundation\Request;

function jsonRequest(string $content): Request
{
    return new Request([], [], [], [], [], ['CONTENT_TYPE' => 'application/json'], $content);
}

it('stores the normalized choice on the cart and recalculates it', function () {
    $cart    = new Cart(Checkout::TOKEN);
    $loader  = new FixedCartLoader($cart);
    $gateway = new FixedDeliveryOptionsGateway();

    $response = (new DeliveryOptionsController($gateway, $loader, new RecordingLogger()))
        ->store(jsonRequest('{"deliveryOptions":{"carrier":"postnl","deliveryType":"evening"}}'), Checkout::context());

    expect($response->getStatusCode())->toBe(204)
        ->and($gateway->normalized)->toBe([['carrier' => 'postnl', 'deliveryType' => 'evening']])
        ->and(CartDeliveryOptions::read($cart))->toBe(['carrier' => 'postnl', 'deliveryType' => 'evening', 'normalized' => true])
        ->and($loader->recalculated)->toBe([$cart]);
});

it('refuses a body that is not json or has no delivery options', function (string $content) {
    $loader = new FixedCartLoader();

    $response = (new DeliveryOptionsController(new FixedDeliveryOptionsGateway(), $loader, new RecordingLogger()))
        ->store(jsonRequest($content), Checkout::context());

    expect($response->getStatusCode())->toBe(400)
        ->and($loader->recalculated)->toBe([]);
})->with(['not json' => ['{'], 'no key' => ['{"other":1}'], 'empty' => ['{"deliveryOptions":{}}'], 'string' => ['{"deliveryOptions":"x"}'], 'list' => ['[1]']]);

it('refuses a choice that the pdk cannot read, and logs it', function () {
    $logger = new RecordingLogger();
    $loader = new FixedCartLoader();

    $response = (new DeliveryOptionsController(new FixedDeliveryOptionsGateway([], new RuntimeException('Unknown carrier')), $loader, $logger))
        ->store(jsonRequest('{"deliveryOptions":{"carrier":"nope"}}'), Checkout::context());

    expect($response->getStatusCode())->toBe(400)
        ->and($loader->recalculated)->toBe([])
        ->and($logger->records[0]['level'])->toBe(LogLevel::WARNING);
});
