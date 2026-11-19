<?php

declare(strict_types=1);

use MyParcel\Shopware\Checkout\Cart\DeliveryOptionsFeeProcessor;
use MyParcel\Shopware\Checkout\CartDeliveryOptions;
use MyParcel\Shopware\Checkout\SalesChannelContextHolder;
use MyParcel\Shopware\Pdk\Currency\EuroCurrency;
use MyParcel\Shopware\Tests\Support\Checkout;
use MyParcel\Shopware\Tests\Support\Entities;
use MyParcel\Shopware\Tests\Support\FakeEntityRepository;
use MyParcel\Shopware\Tests\Support\PdkServices;
use MyParcel\Shopware\Tests\Support\RecordingLogger;
use MyParcelNL\Pdk\Facade\Language;
use MyParcelNL\Pdk\Settings\Model\CarrierSettings;
use MyParcelNL\Pdk\Settings\Model\CheckoutSettings;
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

use function MyParcelNL\Pdk\Tests\factory;

const EVENING = ['carrier' => 'POSTNL', 'deliveryType' => 'evening'];

/**
 * The holder that the processor and the PDK cart repository share.
 */
function feeHolder(bool $reset = false): SalesChannelContextHolder
{
    static $holder = null;

    if ($reset || null === $holder) {
        $holder = new SalesChannelContextHolder();
    }

    return $holder;
}

/**
 * The prices the merchant set for PostNL, net and in euro.
 */
function feePrices(array $prices): void
{
    $carrierSettings = factory(CarrierSettings::class, 'POSTNL');

    foreach ($prices as $key => $price) {
        $carrierSettings->{'with' . ucfirst($key)}($price);
    }

    $carrierSettings->store();
}

beforeEach(function () {
    PdkServices::bootWithCartRepository(feeHolder(true));
    feePrices(['priceDeliveryTypeEvening' => 1.50, 'priceSignature' => 0.40, 'priceDeliveryTypePickup' => -10.00]);
});

function feeProcessor(?RecordingLogger $logger = null): DeliveryOptionsFeeProcessor
{
    $logger ??= new RecordingLogger();

    return new DeliveryOptionsFeeProcessor(
        PdkServices::initializer($logger, feeHolder()),
        feeHolder(),
        new QuantityPriceCalculator(
            new GrossPriceCalculator(new TaxCalculator(), new CashRounding()),
            new NetPriceCalculator(new TaxCalculator(), new CashRounding())
        ),
        new EuroCurrency(FakeEntityRepository::with(Entities::currency('EUR', 1.0))),
        $logger
    );
}

/**
 * Runs the processor on a cart with a stored choice, the way Shopware's
 * Processor does: the extensions are already on the cart to calculate.
 *
 * @param  array<string, mixed> $choice
 */
function processFees(
    array $choice = EVENING,
    ?SalesChannelContext $context = null,
    ?Cart $toCalculate = null,
    ?CartBehavior $behavior = null,
    ?Cart $original = null,
    ?RecordingLogger $logger = null
): Cart {
    $context     ??= Checkout::context();
    $toCalculate ??= Checkout::cart();

    if (null === CartDeliveryOptions::read($toCalculate)) {
        CartDeliveryOptions::store($toCalculate, $choice, $context);
    }

    feeProcessor($logger)->process(new CartDataCollection(), $original ?? new Cart(Checkout::TOKEN), $toCalculate, $context, $behavior ?? new CartBehavior());

    return $toCalculate;
}

function feeLines(Cart $cart): array
{
    return array_values($cart->getLineItems()->filterType(DeliveryOptionsFeeProcessor::LINE_ITEM_TYPE)->getElements());
}

it('adds a line per fee with the label of the pdk, outside the goods', function () {
    $lines = feeLines(processFees(EVENING + ['shipmentOptions' => ['signature' => true]]));

    expect($lines)->toHaveCount(2)
        ->and($lines[0]->getId())->toBe('myparcel-fee-delivery_type_evening')
        ->and($lines[0]->getLabel())->toBe(Language::translate('delivery_options_delivery_type_evening_title'))
        ->and($lines[0]->isGood())->toBeFalse()
        ->and($lines[0]->isRemovable())->toBeFalse()
        ->and($lines[0]->getPayloadValue('feeId'))->toBe('delivery_type_evening')
        ->and($lines[1]->getId())->toBe('myparcel-fee-signature');
});

it('adds the shipping tax to the net fee in a gross context', function () {
    $line = feeLines(processFees())[0];

    // 1.50 net + 21 % = 1.815, rounded to 1.82.
    expect($line->getPrice()->getTotalPrice())->toBe(1.82)
        ->and($line->getPrice()->getCalculatedTaxes()->getAmount())->toBe(0.32)
        ->and($line->getPrice()->getTaxRules()->first()->getTaxRate())->toBe(21.0);
});

it('keeps the net fee in a net context, and Shopware adds the tax on top', function () {
    $line = feeLines(processFees(EVENING, Checkout::context(['taxState' => CartPrice::TAX_STATE_NET])))[0];

    expect($line->getPrice()->getTotalPrice())->toBe(1.5)
        ->and($line->getPrice()->getCalculatedTaxes()->getAmount())->toBe(0.32);
});

it('converts the euro fee to the currency of the context', function () {
    $context = Checkout::context(['currencyFactor' => 2.0, 'taxState' => CartPrice::TAX_STATE_NET]);

    expect(feeLines(processFees(EVENING, $context))[0]->getPrice()->getTotalPrice())->toBe(3.0);
});

it('never lets the pickup discount make the shipping costs negative', function () {
    $line = feeLines(processFees(['carrier' => 'POSTNL', 'deliveryType' => 'pickup']))[0];

    // The shipping costs are 6.05 gross: the discount stops there.
    expect($line->getPrice()->getTotalPrice())->toBe(-6.05);
});

it('gives no pickup discount after a free-shipping promotion', function () {
    // The promotion ran first and set the shipping costs to 0.
    $cart = processFees(['carrier' => 'POSTNL', 'deliveryType' => 'pickup'], null, Checkout::cart(['shippingCosts' => 0.0, 'shippingTax' => 0.0]));

    expect(feeLines($cart))->toBe([]);
});

it('runs after the shipping discounts of promotions', function () {
    $services = file_get_contents(__DIR__ . '/../../../../src/Resources/config/services.xml');

    preg_match('/DeliveryOptionsFeeProcessor">.*?shopware\.cart\.processor" priority="(-?\d+)"/s', $services, $match);

    // Shopware runs the processors from high to low; PromotionDeliveryProcessor has -5100.
    expect((int) $match[1])->toBe(DeliveryOptionsFeeProcessor::PRIORITY)
        ->and(DeliveryOptionsFeeProcessor::PRIORITY)->toBeLessThan(-5100);
});

it('gives the full pickup discount when it is smaller than the shipping costs', function () {
    feePrices(['priceDeliveryTypePickup' => -1.00]);

    expect(feeLines(processFees(['carrier' => 'POSTNL', 'deliveryType' => 'pickup']))[0]->getPrice()->getTotalPrice())->toBe(-1.21);
});

it('adds no line for a fee of 0', function () {
    expect(feeLines(processFees(['carrier' => 'POSTNL', 'deliveryType' => 'standard'])))->toBe([]);
});

it('does nothing for a cart without a choice', function () {
    $cart = Checkout::cart();

    feeProcessor()->process(new CartDataCollection(), new Cart(Checkout::TOKEN), $cart, Checkout::context(), new CartBehavior());

    expect(feeLines($cart))->toBe([]);
});

it('forgets a choice made for another country, and charges nothing for it', function () {
    $cart = Checkout::cart();
    CartDeliveryOptions::store($cart, EVENING, Checkout::context(['countryIso' => 'BE']));

    processFees(EVENING, Checkout::context(), $cart);

    expect(CartDeliveryOptions::read($cart))->toBeNull()
        ->and(feeLines($cart))->toBe([]);
});

it('forgets the choice when the cart no longer gets delivery options, so the order does not get it', function () {
    factory(CheckoutSettings::class)->withEnableDeliveryOptions(false)->store();

    $cart = processFees();

    expect(CartDeliveryOptions::read($cart))->toBeNull()
        ->and(feeLines($cart))->toBe([]);
});

it('logs a failure of the pdk and charges nothing, so the checkout keeps working', function () {
    $logger = new RecordingLogger();
    $cart   = processFees(['carrier' => 'NOT_A_CARRIER', 'deliveryType' => 'evening'], null, null, null, null, $logger);

    expect(feeLines($cart))->toBe([])
        ->and($logger->records[0]['level'])->toBe(LogLevel::ERROR);
});

it('keeps the fee lines of an order that the admin recalculates', function () {
    $original = new Cart(Checkout::TOKEN);
    $kept     = new LineItem('myparcel-fee-delivery_type_evening', DeliveryOptionsFeeProcessor::LINE_ITEM_TYPE, null, 1);
    $original->add($kept);
    $original->add(new LineItem('product-line', LineItem::PRODUCT_LINE_ITEM_TYPE, Entities::PRODUCT_ID, 1));

    $behavior = new CartBehavior([CheckoutPermissions::SKIP_DELIVERY_PRICE_RECALCULATION => true]);
    $cart     = processFees(EVENING, null, new Cart(Checkout::TOKEN), $behavior, $original);

    expect(feeLines($cart))->toBe([$kept]);
});
