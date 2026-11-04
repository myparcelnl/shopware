<?php

declare(strict_types=1);

use MyParcel\Shopware\Entity\OrderExtension;
use MyParcel\Shopware\Tests\Support\Entities;
use MyParcel\Shopware\Tests\Support\FakeEntityRepository;
use MyParcel\Shopware\Tests\Support\RecordingConnection;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;

it('builds shopware entities with the synthetic defaults', function () {
    $order = Entities::order();

    expect($order->getId())->toBe(Entities::ORDER_ID)
        ->and($order->getLineItems())->toHaveCount(2)
        ->and($order->getPrimaryOrderDelivery()?->getShippingOrderAddress()?->getCountry()?->getIso())->toBe('NL')
        ->and($order->getExtension(OrderExtension::SHIPMENTS))->toHaveCount(1)
        ->and(Entities::product()->getPrice()?->first()?->getGross())->toBe(12.10);
});

it('finds entities by id and records writes without a database', function () {
    $currency   = Entities::currency('EUR', 1.0);
    $repository = FakeEntityRepository::with($currency);
    $context    = Context::createDefaultContext();

    $repository->upsert([['id' => 'row-1']], $context);
    $repository->delete([['id' => 'row-2']], $context);

    expect($repository->search(new Criteria([$currency->getId()]), $context)->first())->toBe($currency)
        ->and($repository->search(new Criteria([Entities::id()]), $context)->count())->toBe(0)
        ->and($repository->searchIds(new Criteria(), $context)->firstId())->toBe($currency->getId())
        ->and($repository->upserts)->toBe([['id' => 'row-1']])
        ->and($repository->deletes)->toBe([['id' => 'row-2']]);
});

it('runs a transaction at once and records statements', function () {
    $connection = new RecordingConnection();

    $result = $connection->transactional(static fn () => 'done');
    $connection->executeStatement('SELECT 1');

    expect($result)->toBe('done')
        ->and($connection->transactions)->toBe(1)
        ->and($connection->statements)->toBe([['sql' => 'SELECT 1', 'params' => []]]);
});
