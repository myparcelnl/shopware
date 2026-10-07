<?php

declare(strict_types=1);

use MyParcel\Shopware\Controller\WebhookController;
use MyParcel\Shopware\Pdk\Webhook\Repository\PdkWebhooksRepository;
use MyParcel\Shopware\Pdk\Webhook\WebhookUrlValidator;
use MyParcel\Shopware\Tests\Support\HttpBridgeFactory;
use MyParcel\Shopware\Tests\Support\InMemoryConfigStorage;
use MyParcel\Shopware\Tests\Support\RecordingLogger;
use Psr\Log\LogLevel;
use Symfony\Component\HttpFoundation\Request;

/*
 * Rejection paths only. A valid hash calls callWebhook(), which boots the PDK;
 * the manual test on the local shop covers that path.
 */

const WEBHOOK_BASE_URL = 'https://shop.test/api/myparcel/webhook';

function webhookController(RecordingLogger $logger, InMemoryConfigStorage $storage): WebhookController
{
    return new WebhookController(
        HttpBridgeFactory::create($logger),
        $storage,
        new WebhookUrlValidator(),
        $logger
    );
}

function storageWith(mixed $value): InMemoryConfigStorage
{
    $storage = new InMemoryConfigStorage();

    if (null !== $value) {
        $storage->values[PdkWebhooksRepository::KEY_HASHED_URL] = $value;
    }

    return $storage;
}

it('rejects an empty hash, even when the stored url has no hash segment', function () {
    $logger     = new RecordingLogger();
    $controller = webhookController($logger, storageWith(WEBHOOK_BASE_URL));

    $response = $controller->webhook(Request::create(WEBHOOK_BASE_URL, 'POST'));

    expect($response->getStatusCode())->toBe(404)
        ->and($response->getContent())->toBe('')
        ->and($logger->records)->toHaveCount(1)
        ->and($logger->records[0]['level'])->toBe(LogLevel::WARNING);
});

it('rejects a wrong hash', function () {
    $logger     = new RecordingLogger();
    $controller = webhookController($logger, storageWith(WEBHOOK_BASE_URL . '/right-hash'));

    $response = $controller->webhook(Request::create(WEBHOOK_BASE_URL . '/wrong-hash', 'POST'), 'wrong-hash');

    expect($response->getStatusCode())->toBe(404)
        ->and($response->getContent())->toBe('')
        ->and($logger->records)->toHaveCount(1)
        ->and($logger->records[0]['level'])->toBe(LogLevel::WARNING);
});

it('rejects every hash when no url is stored', function () {
    $logger     = new RecordingLogger();
    $controller = webhookController($logger, storageWith(null));

    $response = $controller->webhook(Request::create(WEBHOOK_BASE_URL . '/any-hash', 'POST'), 'any-hash');

    expect($response->getStatusCode())->toBe(404)
        ->and($logger->records)->toHaveCount(1)
        ->and($logger->records[0]['level'])->toBe(LogLevel::WARNING);
});

it('rejects a stored value that is not a string', function () {
    $logger     = new RecordingLogger();
    $controller = webhookController($logger, storageWith(['not', 'a', 'url']));

    $response = $controller->webhook(Request::create(WEBHOOK_BASE_URL . '/any-hash', 'POST'), 'any-hash');

    expect($response->getStatusCode())->toBe(404)
        ->and($logger->records)->toHaveCount(1);
});

it('keeps the hash and the stored url out of the log', function () {
    $logger     = new RecordingLogger();
    $controller = webhookController($logger, storageWith(WEBHOOK_BASE_URL . '/right-hash'));

    $controller->webhook(Request::create(WEBHOOK_BASE_URL . '/wrong-hash', 'POST'), 'wrong-hash');

    $logged = json_encode($logger->records);

    expect($logged)->not->toContain('wrong-hash')
        ->and($logged)->not->toContain('right-hash')
        ->and($logged)->not->toContain(WEBHOOK_BASE_URL);
});
