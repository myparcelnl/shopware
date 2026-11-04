<?php

declare(strict_types=1);

use MyParcel\Shopware\Tests\Support\TestPdk;
use MyParcelNL\Pdk\App\Order\Model\PdkOrder;
use MyParcelNL\Pdk\Facade\Logger;

it('boots a pdk in which order models compute their totals', function () {
    TestPdk::boot();

    $order = new PdkOrder([
        'shipmentPrice' => 495,
        'lines'         => [
            ['quantity' => 2, 'price' => 1000, 'vat' => 210, 'priceAfterVat' => 1210, 'product' => ['weight' => 10]],
        ],
    ]);

    expect($order->orderPrice)->toBe(2000)
        ->and($order->totalPrice)->toBe(2495)
        ->and($order->physicalProperties->initialWeight)->toBe(20);
});

it('records log entries per level', function () {
    TestPdk::boot();

    Logger::warning('Something to see', ['orderId' => 'abc']);

    expect(TestPdk::logs('warning'))->toHaveCount(1)
        ->and(TestPdk::logs('warning')[0]['context'])->toBe(['orderId' => 'abc']);
});
