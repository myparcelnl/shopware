<?php

declare(strict_types=1);

use MyParcel\Shopware\Pdk\Order\Mapper\AddressMapper;
use MyParcel\Shopware\Pdk\Order\Mapper\MoneyConverter;
use MyParcel\Shopware\Pdk\Order\Mapper\PdkOrderMapper;
use MyParcel\Shopware\Pdk\Product\Mapper\PdkProductMapper;
use MyParcel\Shopware\Tests\Support\Fixtures;
use MyParcel\Shopware\Tests\Support\TestPdk;
use MyParcelNL\Pdk\Base\Service\CurrencyService;
use MyParcelNL\Pdk\Base\Service\WeightService;

beforeEach(fn () => TestPdk::boot());

function orderMapper(): PdkOrderMapper
{
    $currency = new CurrencyService();

    return new PdkOrderMapper(
        new AddressMapper(),
        new MoneyConverter($currency),
        new PdkProductMapper($currency, new WeightService())
    );
}

it('maps the identity and the stored order data', function () {
    $order = orderMapper()->fromSnapshot(Fixtures::snapshot('order-nl'));

    expect($order->externalIdentifier)->toBe('0190a1b2c3d4e5f60718293a4b5c6d01')
        ->and($order->referenceIdentifier)->toBe('10001')
        ->and($order->orderDate->format(DATE_ATOM))->toBe('2026-10-01T12:00:00+00:00')
        ->and($order->apiIdentifier)->toBe('api-uuid-0001')
        ->and($order->exported)->toBeTrue()
        ->and($order->deliveryOptions->deliveryType)->toBe('standard');
});

it('maps the shipping and billing address', function () {
    $order = orderMapper()->fromSnapshot(Fixtures::snapshot('order-nl'));

    expect($order->shippingAddress->toArray())->toMatchArray([
        'street'       => 'Teststraat',
        'number'       => '12',
        'numberSuffix' => 'A',
        'postalCode'   => '1234 AB',
        'city'         => 'Teststad',
        'cc'           => 'NL',
        'person'       => 'Jan Test',
        'email'        => 'test@example.test',
        'phone'        => '+31 6 00000000',
    ])->and($order->billingAddress->person)->toBe('Jan Test');
});

it('keeps only product lines, with unit prices in cents and weight in grams', function () {
    $order = orderMapper()->fromSnapshot(Fixtures::snapshot('order-nl'));

    expect($order->lines)->toHaveCount(1)
        ->and($order->lines->first()->quantity)->toBe(2)
        ->and($order->lines->first()->price)->toBe(1000)
        ->and($order->lines->first()->vat)->toBe(210)
        ->and($order->lines->first()->priceAfterVat)->toBe(1210)
        ->and($order->lines->first()->product->weight)->toBe(250)
        ->and($order->physicalProperties->initialWeight)->toBe(500)
        ->and($order->orderPrice)->toBe(2000);
});

it('maps the shipping costs', function () {
    $order = orderMapper()->fromSnapshot(Fixtures::snapshot('order-nl'));

    expect($order->shipmentPrice)->toBe(409)
        ->and($order->shipmentVat)->toBe(86)
        ->and($order->shipmentPriceAfterVat)->toBe(495);
});

it('loads the stored shipments', function () {
    $order = orderMapper()->fromSnapshot(Fixtures::snapshot('order-nl'));

    expect($order->shipments)->toHaveCount(1)
        ->and($order->shipments->first()->id)->toBe(101);
});

it('carries the customer comment as the first note, then the stored notes, in one collection', function () {
    $snapshot          = Fixtures::snapshot('order-nl');
    $snapshot['notes'] = [['externalIdentifier' => 'n1', 'orderIdentifier' => $snapshot['id'], 'author' => 'webshop', 'note' => 'Ingepakt']];

    $order = orderMapper()->fromSnapshot($snapshot);

    expect($order->notes)->toHaveCount(2)
        ->and($order->notes->first()->toArray())->toMatchArray([
            'externalIdentifier' => 'customer_comment',
            'orderIdentifier'    => '0190a1b2c3d4e5f60718293a4b5c6d01',
            'author'             => 'customer',
            'note'               => 'Graag bij de buren afgeven',
        ])
        ->and($order->notes->last()->note)->toBe('Ingepakt')
        ->and($order->notes)->toBe($order->notes)
        ->and($order->notes->first())->toBe($order->notes->first());
});

it('maps a belgian net order with a variant product', function () {
    $order = orderMapper()->fromSnapshot(Fixtures::snapshot('order-be-net'));

    expect($order->shippingAddress->toArray())->toMatchArray(['street' => 'Kerkstraat', 'number' => '16', 'boxNumber' => '2', 'isBusiness' => true])
        ->and($order->lines->first()->price)->toBe(1000)
        ->and($order->lines->first()->priceAfterVat)->toBe(1210)
        ->and($order->lines->first()->product->mergedSettings->packageType)->toBe('mailbox')
        ->and($order->lines->first()->product->mergedSettings->fitInMailbox)->toBe(0)
        ->and($order->shipmentPrice)->toBe(0);
});

it('maps a number in the first additional line, a foreign currency and a deleted product', function () {
    $order = orderMapper()->fromSnapshot(Fixtures::snapshot('order-additional-line'));

    expect($order->shippingAddress->toArray())->toMatchArray(['street' => 'Teststraat', 'number' => '12', 'streetAdditionalInfo' => 'Achterom'])
        ->and($order->lines->first()->priceAfterVat)->toBe(1200)
        ->and($order->lines->first()->product->name)->toBe('Verwijderd product')
        ->and($order->lines->first()->product->sku)->toBe('SW-OLD')
        ->and($order->lines->first()->product->weight)->toBe(0)
        ->and($order->shipmentPriceAfterVat)->toBe(500);
});

it('logs a failed split with the order id only', function () {
    $snapshot                              = Fixtures::snapshot('order-nl');
    $snapshot['shippingAddress']['street'] = 'Teststraat';

    orderMapper()->fromSnapshot($snapshot);

    $warnings = TestPdk::logs('warning');

    expect($warnings)->toHaveCount(1)
        ->and($warnings[0]['context'])->toBe(['orderId' => '0190a1b2c3d4e5f60718293a4b5c6d01'])
        ->and($warnings[0]['message'])->not->toContain('Teststraat');
});

it('maps an order without a delivery', function () {
    $snapshot                    = Fixtures::snapshot('order-nl');
    $snapshot['shippingAddress'] = null;
    $snapshot['shippingCosts']   = null;

    $order = orderMapper()->fromSnapshot($snapshot);

    expect($order->shippingAddress->street)->toBeNull()
        ->and($order->shippingAddress->email)->toBe('test@example.test')
        ->and($order->shipmentPrice)->toBe(0);
});

it('maps a snapshot that was exported from the demo shop', function () {
    $snapshot = Fixtures::snapshot('order-demo');
    $order    = orderMapper()->fromSnapshot($snapshot);

    expect($order->externalIdentifier)->toBe($snapshot['id'])
        ->and($order->shippingAddress->street)->not->toBeNull()
        ->and($order->lines->count())->toBeGreaterThan(0)
        ->and($order->totalPriceAfterVat)->toBeGreaterThan(0);
});
