<?php

declare(strict_types=1);

use MyParcel\Shopware\Pdk\Webhook\WebhookUrlValidator;

function storedUrl(): string
{
    return 'https://shop.test/api/myparcel/webhook/0123456789abcdef0123456789abcdef';
}

it('accepts the stored path', function () {
    expect((new WebhookUrlValidator())->isValid(storedUrl(), '/api/myparcel/webhook/0123456789abcdef0123456789abcdef'))
        ->toBeTrue();
});

it('accepts the stored path under a base path', function () {
    expect((new WebhookUrlValidator())->isValid(
        'https://shop.test/shop/api/myparcel/webhook/abc',
        '/shop/api/myparcel/webhook/abc'
    ))->toBeTrue();
});

it('rejects another hash', function () {
    expect((new WebhookUrlValidator())->isValid(storedUrl(), '/api/myparcel/webhook/ffffffffffffffffffffffffffffffff'))
        ->toBeFalse();
});

it('rejects the hash in another case', function () {
    expect((new WebhookUrlValidator())->isValid(storedUrl(), '/api/myparcel/webhook/0123456789ABCDEF0123456789ABCDEF'))
        ->toBeFalse();
});

it('rejects a request without a hash', function () {
    expect((new WebhookUrlValidator())->isValid(storedUrl(), '/api/myparcel/webhook'))->toBeFalse();
});

it('rejects every request while no url is stored', function () {
    expect((new WebhookUrlValidator())->isValid(null, '/api/myparcel/webhook/abc'))->toBeFalse()
        ->and((new WebhookUrlValidator())->isValid('', '/api/myparcel/webhook/abc'))->toBeFalse();
});

it('rejects a stored url with an empty hash', function () {
    expect((new WebhookUrlValidator())->isValid('https://shop.test/api/myparcel/webhook/', '/api/myparcel/webhook/'))
        ->toBeFalse();
});

it('rejects an extra query string', function () {
    expect((new WebhookUrlValidator())->isValid(storedUrl(), '/api/myparcel/webhook/0123456789abcdef0123456789abcdef?x=1'))
        ->toBeFalse();
});

it('requires the stored query string too', function () {
    $stored = 'https://shop.test/api/myparcel/webhook/abc?lang=nl';

    expect((new WebhookUrlValidator())->isValid($stored, '/api/myparcel/webhook/abc?lang=nl'))->toBeTrue()
        ->and((new WebhookUrlValidator())->isValid($stored, '/api/myparcel/webhook/abc'))->toBeFalse();
});
