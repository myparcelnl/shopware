<?php

declare(strict_types=1);

use MyParcel\Shopware\Pdk\Order\OrderStatusService;
use MyParcel\Shopware\Pdk\Placeholder\NotImplementedException;
use MyParcel\Shopware\Tests\Support\FixedOrderStatusProvider;
use MyParcelNL\Pdk\App\Order\Contract\OrderStatusServiceInterface;

it('is a pdk order status service', function () {
    expect(new OrderStatusService(new FixedOrderStatusProvider()))->toBeInstanceOf(OrderStatusServiceInterface::class);
});

it('lists the shopware order states', function () {
    $statuses = ['cancelled' => 'Cancelled', 'completed' => 'Done', 'in_progress' => 'In progress', 'open' => 'Open'];

    expect((new OrderStatusService(new FixedOrderStatusProvider($statuses)))->all())->toBe($statuses);
});

it('does not change order states yet', function () {
    expect(fn () => (new OrderStatusService(new FixedOrderStatusProvider()))->updateStatus(['1'], 'completed'))
        ->toThrow(NotImplementedException::class, 'OrderStatusServiceInterface');
});
