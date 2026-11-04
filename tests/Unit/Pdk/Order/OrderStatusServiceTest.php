<?php

declare(strict_types=1);

use MyParcel\Shopware\Pdk\Order\OrderStatusService;
use MyParcel\Shopware\Tests\Support\FakeEntityRepository;
use MyParcel\Shopware\Tests\Support\FixedLocaleResolver;
use MyParcel\Shopware\Tests\Support\RecordingOrderStateTransitioner;
use Symfony\Component\HttpFoundation\RequestStack;

it('moves the orders to the status with the state transitioner', function () {
    $transitioner = new RecordingOrderStateTransitioner();

    (new OrderStatusService(
        FakeEntityRepository::with(),
        FakeEntityRepository::with(),
        new RequestStack(),
        new FixedLocaleResolver(null),
        $transitioner
    ))->updateStatus([1, 'abc'], 'completed');

    expect($transitioner->calls)->toBe([['orderIds' => ['1', 'abc'], 'technicalName' => 'completed']]);
});
