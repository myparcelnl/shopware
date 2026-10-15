<?php

declare(strict_types=1);

use MyParcel\Shopware\Pdk\Routing\RouteName;
use MyParcel\Shopware\Pdk\Webhook\Service\PdkWebhookService;
use MyParcel\Shopware\Tests\Support\FixedUrlResolver;
use MyParcelNL\Pdk\App\Webhook\Contract\PdkWebhookServiceInterface;

function webhookService(): PdkWebhookService
{
    return new PdkWebhookService(new FixedUrlResolver([
        RouteName::WEBHOOK => 'https://shop.test/api/myparcel/webhook',
    ]));
}

it('is a pdk webhook service', function () {
    expect(webhookService())->toBeInstanceOf(PdkWebhookServiceInterface::class);
});

it('uses the webhook route without a hash as base url', function () {
    expect(webhookService()->getBaseUrl())->toBe('https://shop.test/api/myparcel/webhook');
});

it('appends a new hash as the last path segment', function () {
    $url = webhookService()->createUrl();

    expect($url)->toMatch('#^https://shop\.test/api/myparcel/webhook/[0-9a-f]{32}$#');
});

it('creates a different hash every time', function () {
    expect(webhookService()->createUrl())->not->toBe(webhookService()->createUrl());
});
