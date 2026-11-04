<?php

declare(strict_types=1);

use MyParcel\Shopware\Checkout\Cart\DeliveryOptionsFeeProcessor;
use MyParcel\Shopware\Checkout\CartDeliveryOptions;
use MyParcel\Shopware\Checkout\DeliveryOptionsFee;
use MyParcel\Shopware\Pdk\Currency\EuroCurrency;
use MyParcel\Shopware\Tests\Support\Checkout;
use MyParcel\Shopware\Tests\Support\Entities;
use MyParcel\Shopware\Tests\Support\FakeEntityRepository;
use MyParcel\Shopware\Tests\Support\FixedDeliveryOptionsGateway;
use MyParcel\Shopware\Tests\Support\RecordingLogger;
use Psr\Log\LogLevel;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\CartBehavior;
use Shopware\Core\Checkout\Cart\LineItem\CartDataCollection;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\Price\CashRounding;
use Shopware\Core\Checkout\Cart\Price\GrossPriceCalculator;
use Shopware\Core\Checkout\Cart\Price\NetPriceCalculator;
use Shopware\Core\Checkout\Cart\Price\QuantityPriceCalculator;
use Shopware\Core\Checkout\Cart\Price\Struct\CartPrice;
use Shopware\Core\Checkout\Cart\Tax\TaxCalculator;
use Shopware\Core\Checkout\CheckoutPermissions;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

function feeProcessor(FixedDeliveryOptionsGateway $gateway, ?RecordingLogger $logger = null): DeliveryOptionsFeeProcessor
{
    return new DeliveryOptionsFeeProcessor(
        $gateway,
        new QuantityPriceCalculator(
            new GrossPriceCalculator(new TaxCalculator(), new CashRounding()),
            new NetPriceCalculator(new TaxCalculator(), new CashRounding())
        ),
        new EuroCurrency(FakeEntityRepository::with(Entities::currency('EUR', 1.0))),
        $logger ?? new RecordingLogger()
    );
}

/**
 * Runs the processor on a cart with a stored choice, the way Shopware's
 * Processor does: the extensions are already on the cart to calculate.
 */
function processFees(
    FixedDeliveryOptionsGateway $gateway,
    ?SalesChannelContext $context = null,
    ?Cart $toCalculate = null,
    ?CartBehavior $behavior = null,
    ?Cart $original = null,
    ?RecordingLogger $logger = null
): Cart {
    $context     ??= Checkout::context();
    $toCalculate ??= Checkout::cart();

    if (null === CartDeliveryOptions::read($toCalculate)) {
        CartDeliveryOptions::store($toCalculate, ['carrier' => 'POSTNL', 'deliveryType' => 'evening'], $context);
    }

    feeProcessor($gateway, $logger)->process(new CartDataCollection(), $original ?? new Cart(Checkout::TOKEN), $toCalculate, $context, $behavior ?? new CartBehavior());

    return $toCalculate;
}

function feeLines(Cart $cart): array
{
    return array_values($cart->getLineItems()->filterType(DeliveryOptionsFeeProcessor::LINE_ITEM_TYPE)->getElements());
}

it('adds a line per fee with the label of the pdk, outside the goods', function () {
    $cart = processFees(new FixedDeliveryOptionsGateway([
        new DeliveryOptionsFee('delivery_type_evening', 'Evening delivery', 1.50),
        new DeliveryOptionsFee('signature', 'Signature', 0.40),
    ]));

    $lines = feeLines($cart);

    expect($lines)->toHaveCount(2)
        ->and($lines[0]->getId())->toBe('myparcel-fee-delivery_type_evening')
        ->and($lines[0]->getLabel())->toBe('Evening delivery')
        ->and($lines[0]->isGood())->toBeFalse()
        ->and($lines[0]->isRemovable())->toBeFalse()
        ->and($lines[0]->getPayloadValue('feeId'))->toBe('delivery_type_evening')
        ->and($lines[1]->getLabel())->toBe('Signature');
});

it('adds the shipping tax to the net fee in a gross context', function () {
    $line = feeLines(processFees(new FixedDeliveryOptionsGateway([new DeliveryOptionsFee('delivery_type_evening', 'Evening', 1.50)])))[0];

    // 1.50 net + 21 % = 1.815, rounded to 1.82.
    expect($line->getPrice()->getTotalPrice())->toBe(1.82)
        ->and($line->getPrice()->getCalculatedTaxes()->getAmount())->toBe(0.32)
        ->and($line->getPrice()->getTaxRules()->first()->getTaxRate())->toBe(21.0);
});

it('keeps the net fee in a net context, and Shopware adds the tax on top', function () {
    $context = Checkout::context(['taxState' => CartPrice::TAX_STATE_NET]);
    $line    = feeLines(processFees(new FixedDeliveryOptionsGateway([new DeliveryOptionsFee('delivery_type_evening', 'Evening', 1.50)]), $context))[0];

    expect($line->getPrice()->getTotalPrice())->toBe(1.5)
        ->and($line->getPrice()->getCalculatedTaxes()->getAmount())->toBe(0.32);
});

it('converts the euro fee to the currency of the context', function () {
    $context = Checkout::context(['currencyFactor' => 2.0, 'taxState' => CartPrice::TAX_STATE_NET]);
    $line    = feeLines(processFees(new FixedDeliveryOptionsGateway([new DeliveryOptionsFee('delivery_type_evening', 'Evening', 1.50)]), $context))[0];

    expect($line->getPrice()->getTotalPrice())->toBe(3.0);
});

it('never lets the pickup discount make the shipping costs negative', function () {
    $line = feeLines(processFees(new FixedDeliveryOptionsGateway([new DeliveryOptionsFee(DeliveryOptionsFee::PICKUP_ID, 'Pickup', -10.00)])))[0];

    // The shipping costs are 6.05 gross: the discount stops there.
    expect($line->getPrice()->getTotalPrice())->toBe(-6.05);
});

it('gives the full pickup discount when it is smaller than the shipping costs', function () {
    $line = feeLines(processFees(new FixedDeliveryOptionsGateway([new DeliveryOptionsFee(DeliveryOptionsFee::PICKUP_ID, 'Pickup', -1.00)])))[0];

    expect($line->getPrice()->getTotalPrice())->toBe(-1.21);
});

it('adds no line for a fee of 0', function () {
    expect(feeLines(processFees(new FixedDeliveryOptionsGateway([new DeliveryOptionsFee('delivery_type_standard', 'Standard', 0.0)]))))
        ->toBe([]);
});

it('does nothing for a cart without a choice', function () {
    $gateway = new FixedDeliveryOptionsGateway([new DeliveryOptionsFee('delivery_type_evening', 'Evening', 1.50)]);
    $cart    = Checkout::cart();

    feeProcessor($gateway)->process(new CartDataCollection(), new Cart(Checkout::TOKEN), $cart, Checkout::context(), new CartBehavior());

    expect(feeLines($cart))->toBe([])
        ->and($gateway->feesAskedFor)->toBe([]);
});

it('forgets a choice made for another country, and charges nothing for it', function () {
    $gateway = new FixedDeliveryOptionsGateway([new DeliveryOptionsFee('delivery_type_evening', 'Evening', 1.50)]);
    $cart    = Checkout::cart();
    CartDeliveryOptions::store($cart, ['carrier' => 'POSTNL'], Checkout::context(['countryIso' => 'BE']));

    processFees($gateway, Checkout::context(), $cart);

    expect(CartDeliveryOptions::read($cart))->toBeNull()
        ->and(feeLines($cart))->toBe([])
        ->and($gateway->feesAskedFor)->toBe([]);
});

it('logs a failure of the pdk and charges nothing, so the checkout keeps working', function () {
    $logger = new RecordingLogger();
    $cart   = processFees(new FixedDeliveryOptionsGateway([], new RuntimeException('Unknown carrier')), null, null, null, null, $logger);

    expect(feeLines($cart))->toBe([])
        ->and($logger->records[0]['level'])->toBe(LogLevel::ERROR);
});

it('keeps the fee lines of an order that the admin recalculates', function () {
    $gateway  = new FixedDeliveryOptionsGateway([new DeliveryOptionsFee('delivery_type_evening', 'Evening', 9.99)]);
    $original = new Cart(Checkout::TOKEN);
    $kept     = new LineItem('myparcel-fee-delivery_type_evening', DeliveryOptionsFeeProcessor::LINE_ITEM_TYPE, null, 1);
    $original->add($kept);
    $original->add(new LineItem('product-line', LineItem::PRODUCT_LINE_ITEM_TYPE, Entities::PRODUCT_ID, 1));

    $behavior = new CartBehavior([CheckoutPermissions::SKIP_DELIVERY_PRICE_RECALCULATION => true]);
    $cart     = processFees($gateway, null, new Cart(Checkout::TOKEN), $behavior, $original);

    expect(feeLines($cart))->toBe([$kept])
        ->and($gateway->feesAskedFor)->toBe([]);
});
