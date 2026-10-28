<?php

declare(strict_types=1);

use MyParcel\Shopware\Pdk\Order\Mapper\AddressMapper;
use MyParcel\Shopware\Pdk\Order\Mapper\MoneyConverter;
use MyParcel\Shopware\Pdk\Order\Mapper\PdkOrderMapper;
use MyParcel\Shopware\Pdk\Order\Mapper\StreetSplitter;
use MyParcel\Shopware\Tests\Support\Entities;
use MyParcel\Shopware\Tests\Support\TestPdk;
use MyParcelNL\Pdk\App\Order\Model\PdkOrder;
use MyParcelNL\Pdk\App\Order\Model\PdkProduct;
use MyParcelNL\Pdk\Base\Service\CurrencyService;
use Shopware\Core\Checkout\Cart\Price\Struct\CartPrice;
use Shopware\Core\Checkout\Order\OrderEntity;

beforeEach(fn () => TestPdk::boot());

/**
 * Maps the order with the default product of Entities: 250 g, package type mailbox.
 *
 * @param  array<string, PdkProduct> $products
 */
function mapOrder(OrderEntity $order, ?array $products = null, float $euroFactor = 1.0): PdkOrder
{
    $products ??= [Entities::PRODUCT_ID => new PdkProduct([
        'externalIdentifier' => Entities::PRODUCT_ID,
        'sku'                => 'SW-1',
        'weight'             => 250,
        'settings'           => ['packageType' => 'mailbox'],
    ])];

    return (new PdkOrderMapper(new AddressMapper(new StreetSplitter()), new MoneyConverter(new CurrencyService())))
        ->map($order, $products, $euroFactor);
}

it('maps the identity and the stored order data', function () {
    $order = mapOrder(Entities::order());

    expect($order->externalIdentifier)->toBe(Entities::ORDER_ID)
        ->and($order->referenceIdentifier)->toBe('10001')
        ->and($order->orderDate->format(DATE_ATOM))->toBe('2026-10-01T12:00:00+00:00')
        ->and($order->apiIdentifier)->toBe('api-uuid-0001')
        ->and($order->exported)->toBeTrue()
        ->and($order->deliveryOptions->deliveryType)->toBe('standard');
});

it('maps the shipping and billing address', function () {
    $order = mapOrder(Entities::order());

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

it('keeps only product lines, with unit prices in cents and the product of the line', function () {
    $order = mapOrder(Entities::order());

    expect($order->lines)->toHaveCount(1)
        ->and($order->lines->first()->quantity)->toBe(2)
        ->and($order->lines->first()->price)->toBe(1000)
        ->and($order->lines->first()->vat)->toBe(210)
        ->and($order->lines->first()->priceAfterVat)->toBe(1210)
        ->and($order->lines->first()->product->weight)->toBe(250)
        ->and($order->lines->first()->product->mergedSettings->packageType)->toBe('mailbox')
        ->and($order->physicalProperties->initialWeight)->toBe(500)
        ->and($order->orderPrice)->toBe(2000);
});

it('maps the shipping costs', function () {
    $order = mapOrder(Entities::order());

    expect($order->shipmentPrice)->toBe(409)
        ->and($order->shipmentVat)->toBe(86)
        ->and($order->shipmentPriceAfterVat)->toBe(495);
});

it('loads the stored shipments', function () {
    $order = mapOrder(Entities::order());

    expect($order->shipments)->toHaveCount(1)
        ->and($order->shipments->first()->id)->toBe(101);
});

it('carries the customer comment as the first note, then the stored notes, in one collection', function () {
    $order = mapOrder(Entities::order([
        'notes' => [['externalIdentifier' => 'n1', 'orderIdentifier' => Entities::ORDER_ID, 'author' => 'webshop', 'note' => 'Ingepakt']],
    ]));

    expect($order->notes)->toHaveCount(2)
        ->and($order->notes->first()->toArray())->toMatchArray([
            'externalIdentifier' => 'customer_comment',
            'orderIdentifier'    => Entities::ORDER_ID,
            'author'             => 'customer',
            'note'               => 'Graag bij de buren afgeven',
        ])
        ->and($order->notes->last()->note)->toBe('Ingepakt')
        ->and($order->notes)->toBe($order->notes)
        ->and($order->notes->first())->toBe($order->notes->first());
});

it('maps a belgian net order with a variant product', function () {
    $variantId = Entities::id();
    $order     = mapOrder(
        Entities::order([
            'taxStatus'       => CartPrice::TAX_STATE_NET,
            'billingAddress'  => null,
            'primaryDelivery' => Entities::delivery(
                Entities::address(['street' => 'Kerkstraat 16 bus 2', 'countryIso' => 'BE', 'company' => 'Test BV', 'zipcode' => '1000']),
                Entities::price(0.0)
            ),
            'lineItems'       => [Entities::lineItem(['productId' => $variantId, 'quantity' => 1, 'price' => Entities::price(10.00, 1, 2.10)])],
            'orderData'       => null,
            'shipments'       => [],
        ]),
        [$variantId => new PdkProduct([
            'externalIdentifier' => $variantId,
            'weight'             => 1000,
            'settings'           => ['fitInMailbox' => 0],
            'parent'             => ['externalIdentifier' => Entities::id(), 'settings' => ['packageType' => 'mailbox', 'fitInMailbox' => 3]],
        ])]
    );

    expect($order->shippingAddress->toArray())->toMatchArray(['street' => 'Kerkstraat', 'number' => '16', 'boxNumber' => '2', 'isBusiness' => true])
        ->and($order->lines->first()->price)->toBe(1000)
        ->and($order->lines->first()->priceAfterVat)->toBe(1210)
        ->and($order->lines->first()->product->mergedSettings->packageType)->toBe('mailbox')
        ->and($order->lines->first()->product->mergedSettings->fitInMailbox)->toBe(0)
        ->and($order->shipmentPrice)->toBe(0);
});

it('maps a number in the first additional line, a foreign currency and a deleted product', function () {
    $order = mapOrder(
        Entities::order([
            'currencyFactor'  => 0.875,
            'primaryDelivery' => Entities::delivery(
                Entities::address(['street' => 'Teststraat', 'additionalAddressLine1' => '12', 'additionalAddressLine2' => 'Achterom']),
                Entities::price(4.375, 1, 0.76)
            ),
            'lineItems'       => [Entities::lineItem([
                'label'         => 'Verwijderd product',
                'productNumber' => 'SW-OLD',
                'quantity'      => 1,
                'price'         => Entities::price(10.50, 1, 1.82),
            ])],
        ]),
        []
    );

    expect($order->shippingAddress->toArray())->toMatchArray(['street' => 'Teststraat', 'number' => '12', 'streetAdditionalInfo' => 'Achterom'])
        ->and($order->lines->first()->priceAfterVat)->toBe(1200)
        ->and($order->lines->first()->product->name)->toBe('Verwijderd product')
        ->and($order->lines->first()->product->sku)->toBe('SW-OLD')
        ->and($order->lines->first()->product->weight)->toBe(0)
        ->and($order->shipmentPriceAfterVat)->toBe(500);
});

it('converts the amounts to euro when the default currency is not euro', function () {
    // Default currency USD; the order is in GBP. 1 USD = 0.8 GBP = 0.9 EUR.
    $order = mapOrder(
        Entities::order([
            'currencyFactor'  => 0.8,
            'primaryDelivery' => Entities::delivery(Entities::address(), Entities::price(4.00, 1, 0.69)),
            'lineItems'       => [Entities::lineItem(['quantity' => 1, 'price' => Entities::price(8.00, 1, 1.39)])],
        ]),
        null,
        0.9
    );

    expect($order->lines->first()->priceAfterVat)->toBe(900)
        ->and($order->lines->first()->vat)->toBe(156)
        ->and($order->shipmentPriceAfterVat)->toBe(450);
});

it('gives the gross total of shopware for a line with a quantity above one', function () {
    // 3 x 9.99 gross at 21%: the tax of the position is 5.20, which is not a whole
    // number of cents per unit.
    $order = mapOrder(Entities::order([
        'primaryDelivery' => Entities::delivery(Entities::address(), Entities::price(4.95, 1, 0.86)),
        'lineItems'       => [Entities::lineItem(['quantity' => 3, 'price' => Entities::price(9.99, 3, 5.20)])],
    ]));

    expect($order->lines->first()->priceAfterVat)->toBe(999)
        ->and($order->orderPriceAfterVat)->toBe(2997)
        ->and($order->totalPriceAfterVat)->toBe(3492);
});

it('keeps the net total within half a cent per unit of shopware for a line with a quantity above one', function () {
    // 3 x 9.99 net at 21%: Shopware's tax of the position is 6.29, so its gross
    // total is 36.26.
    $order = mapOrder(Entities::order([
        'taxStatus'       => CartPrice::TAX_STATE_NET,
        'primaryDelivery' => null,
        'lineItems'       => [Entities::lineItem(['quantity' => 3, 'price' => Entities::price(9.99, 3, 6.29)])],
    ]));

    expect($order->lines->first()->price)->toBe(999)
        ->and(abs($order->orderPriceAfterVat - 3626))->toBeLessThanOrEqual(1.5);
});

it('uses the delivery with the highest shipping costs when there is no primary delivery', function () {
    $cheap     = Entities::delivery(Entities::address(['street' => 'Goedkoopstraat 1']), Entities::price(2.00, 1, 0.35));
    $expensive = Entities::delivery(Entities::address(['street' => 'Duurstraat 2']), Entities::price(6.05, 1, 1.05));

    $order = mapOrder(Entities::order(['primaryDelivery' => null, 'deliveries' => [$cheap, $expensive]]));

    expect($order->shippingAddress->street)->toBe('Duurstraat')
        ->and($order->shipmentPriceAfterVat)->toBe(605);
});

it('logs a failed split with the order id only', function () {
    mapOrder(Entities::order(['primaryDelivery' => Entities::delivery(Entities::address(['street' => 'Teststraat']), Entities::price(4.95))]));

    $warnings = TestPdk::logs('warning');

    expect($warnings)->toHaveCount(1)
        ->and($warnings[0]['context'])->toBe(['orderId' => Entities::ORDER_ID])
        ->and($warnings[0]['message'])->not->toContain('Teststraat');
});

it('maps an order without a delivery', function () {
    $order = mapOrder(Entities::order(['primaryDelivery' => null]));

    expect($order->shippingAddress->street)->toBeNull()
        ->and($order->shippingAddress->email)->toBe('test@example.test')
        ->and($order->shipmentPrice)->toBe(0);
});
