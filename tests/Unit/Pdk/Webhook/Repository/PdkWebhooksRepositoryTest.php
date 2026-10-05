<?php

declare(strict_types=1);

use MyParcel\Shopware\Pdk\Webhook\Repository\PdkWebhooksRepository;
use MyParcel\Shopware\Tests\Support\InMemoryConfigStorage;
use MyParcelNL\Pdk\App\Webhook\Repository\AbstractPdkWebhooksRepository;
use MyParcelNL\Pdk\Storage\MemoryCacheStorage;
use MyParcelNL\Pdk\Webhook\Collection\WebhookSubscriptionCollection;
use MyParcelNL\Pdk\Webhook\Repository\WebhookSubscriptionRepository;
use PHPUnit\Framework\TestCase;

/**
 * The API repository is never called: this class only stores what it is given.
 */
function webhooksRepository(TestCase $test, InMemoryConfigStorage $config): PdkWebhooksRepository
{
    return new PdkWebhooksRepository(
        new MemoryCacheStorage(),
        $test->getMockBuilder(WebhookSubscriptionRepository::class)->disableOriginalConstructor()->getMock(),
        $config
    );
}

function subscriptions(): WebhookSubscriptionCollection
{
    return new WebhookSubscriptionCollection([
        ['hook' => 'shipment_status_change', 'url' => 'https://shop.test/api/myparcel/webhook/abc'],
        ['hook' => 'order_status_change', 'url' => 'https://shop.test/api/myparcel/webhook/abc'],
    ]);
}

it('is a pdk webhooks repository', function () {
    expect(webhooksRepository($this, new InMemoryConfigStorage()))->toBeInstanceOf(AbstractPdkWebhooksRepository::class);
});

it('has no hashed url when none was stored', function () {
    expect(webhooksRepository($this, new InMemoryConfigStorage())->getHashedUrl())->toBeNull();
});

it('reads a stored hashed url back in a new request', function () {
    $config = new InMemoryConfigStorage();
    webhooksRepository($this, $config)->storeHashedUrl('https://shop.test/api/myparcel/webhook/abc');

    expect($config->values['webhook_hash'])->toBe('https://shop.test/api/myparcel/webhook/abc')
        ->and(webhooksRepository($this, $config)->getHashedUrl())->toBe('https://shop.test/api/myparcel/webhook/abc');
});

it('ignores a stored hashed url that is not a string', function () {
    $config                         = new InMemoryConfigStorage();
    $config->values['webhook_hash'] = ['not' => 'a string'];

    expect(webhooksRepository($this, $config)->getHashedUrl())->toBeNull();
});

it('has no subscriptions when none were stored', function () {
    expect(webhooksRepository($this, new InMemoryConfigStorage())->getAll()->all())->toBe([]);
});

it('reads stored subscriptions back in a new request', function () {
    $config = new InMemoryConfigStorage();
    webhooksRepository($this, $config)->store(subscriptions());

    $all = webhooksRepository($this, $config)->getAll();

    expect($all)->toHaveCount(2)
        ->and($all->first()->hook)->toBe('shipment_status_change')
        ->and(webhooksRepository($this, $config)->has('order_status_change'))->toBeTrue();
});

it('returns the new subscriptions after a store in the same request', function () {
    $repository = webhooksRepository($this, new InMemoryConfigStorage());
    $repository->getAll();

    $repository->store(subscriptions());

    expect($repository->getAll())->toHaveCount(2);
});

it('removes one hook and keeps the others', function () {
    $config = new InMemoryConfigStorage();
    webhooksRepository($this, $config)->store(subscriptions());

    webhooksRepository($this, $config)->remove('shipment_status_change');

    $repository = webhooksRepository($this, $config);

    expect($repository->has('shipment_status_change'))->toBeFalse()
        ->and($repository->has('order_status_change'))->toBeTrue();
});
