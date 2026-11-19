<?php

declare(strict_types=1);

namespace MyParcel\Shopware\Checkout\Cart;

use MyParcel\Shopware\Checkout\CartDeliveryOptions;
use MyParcel\Shopware\Checkout\SalesChannelContextHolder;
use MyParcel\Shopware\Pdk\Cart\DeliveryOptionsEligibility;
use MyParcel\Shopware\Pdk\Currency\EuroCurrency;
use MyParcel\Shopware\Pdk\PdkInitializer;
use MyParcel\Shopware\Pdk\Tax\ShippingTaxRate;
use MyParcelNL\Pdk\App\Cart\Contract\PdkCartRepositoryInterface;
use MyParcelNL\Pdk\App\Cart\Model\PdkCartFee;
use MyParcelNL\Pdk\App\DeliveryOptions\Contract\DeliveryOptionsFeesServiceInterface;
use MyParcelNL\Pdk\Facade\Language;
use MyParcelNL\Pdk\Facade\Pdk;
use MyParcelNL\Pdk\Shipment\Model\DeliveryOptions;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\CartBehavior;
use Shopware\Core\Checkout\Cart\CartProcessorInterface;
use Shopware\Core\Checkout\Cart\Delivery\Struct\Delivery;
use Shopware\Core\Checkout\Cart\LineItem\CartDataCollection;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\Price\QuantityPriceCalculator;
use Shopware\Core\Checkout\Cart\Price\Struct\CartPrice;
use Shopware\Core\Checkout\Cart\Price\Struct\QuantityPriceDefinition;
use Shopware\Core\Checkout\CheckoutPermissions;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Throwable;

/**
 * Adds the fees of the chosen delivery options to the cart, one line per fee,
 * as the WooCommerce plugin does.
 *
 * It runs after Shopware's DeliveryProcessor (-5000) and after the shipping
 * discounts of promotions (-5100), so the shipping costs are final. A fee is net
 * and in euro; the line gets the tax rules of the shipping costs. The pickup
 * discount never makes the shipping costs negative, also not after a
 * free-shipping promotion.
 *
 * The fees and the order data follow one rule: a choice that no longer applies
 * to the cart is forgotten, so the order never gets a choice without its fees.
 *
 * Shopware calculates a cart from the start on every change, so every run adds
 * the lines again. When the admin recalculates an order, Shopware keeps the
 * shipping costs, and this processor keeps the fee lines of the order as they are.
 */
final class DeliveryOptionsFeeProcessor implements CartProcessorInterface
{
    public const LINE_ITEM_TYPE = 'myparcel_fee';

    public const PRIORITY = -5150;

    private const PICKUP_FEE_ID = 'delivery_type_pickup';

    public function __construct(
        private readonly PdkInitializer $pdkInitializer,
        private readonly SalesChannelContextHolder $contextHolder,
        private readonly QuantityPriceCalculator $priceCalculator,
        private readonly EuroCurrency $euroCurrency,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Adds the fee lines for the stored choice. Keeps the fee lines of the
     * order when the admin recalculates it, forgets a choice for another
     * shipping method or country, or for a cart without delivery options, and
     * adds nothing when the PDK fails, so the checkout keeps working.
     */
    public function process(CartDataCollection $data, Cart $original, Cart $toCalculate, SalesChannelContext $context, CartBehavior $behavior): void
    {
        if ($behavior->hasPermission(CheckoutPermissions::SKIP_DELIVERY_PRICE_RECALCULATION)) {
            foreach ($original->getLineItems()->filterType(self::LINE_ITEM_TYPE) as $lineItem) {
                $toCalculate->add($lineItem);
            }

            return;
        }

        $deliveryOptions = CartDeliveryOptions::read($toCalculate);

        if (null === $deliveryOptions) {
            return;
        }

        if (!CartDeliveryOptions::matches($toCalculate, $context)) {
            CartDeliveryOptions::forget($toCalculate);

            return;
        }

        $delivery = $toCalculate->getDeliveries()->first();

        if (null === $delivery) {
            return;
        }

        try {
            $fees = $this->fees($toCalculate, $context, $deliveryOptions);
        } catch (Throwable $exception) {
            $this->logger->error('MyParcel: could not calculate the delivery option fees', ['exception' => $exception]);

            return;
        }

        if (null === $fees) {
            CartDeliveryOptions::forget($toCalculate);

            return;
        }

        $factor = $context->getCurrency()->getFactor() / $this->euroCurrency->getFactor();

        foreach ($fees as $fee) {
            $lineItem = $this->createLineItem($fee, $delivery, $context, $factor);

            if (null !== $lineItem) {
                $toCalculate->add($lineItem);
            }
        }
    }

    /**
     * The fees of the choice, as the WooCommerce plugin gets them. Null when the
     * cart gets no delivery options: then the choice no longer applies.
     *
     * @param  array<string, mixed> $deliveryOptions DeliveryOptions::toStorableArray()
     *
     * @return null|list<PdkCartFee>
     */
    private function fees(Cart $cart, SalesChannelContext $context, array $deliveryOptions): ?array
    {
        // The PDK cart repository reads the context in the holder.
        $this->contextHolder->set($context);
        $this->pdkInitializer->boot();

        /** @var PdkCartRepositoryInterface $cartRepository */
        $cartRepository = Pdk::get(PdkCartRepositoryInterface::class);

        if (!DeliveryOptionsEligibility::isEligible($cartRepository->get($cart))) {
            return null;
        }

        /** @var DeliveryOptionsFeesServiceInterface $feesService */
        $feesService = Pdk::get(DeliveryOptionsFeesServiceInterface::class);

        return array_values($feesService->getFees(new DeliveryOptions($deliveryOptions))->all());
    }

    /**
     * Null for a fee of 0: the cart shows no line for it. The fee is the net
     * euro amount that the merchant set.
     */
    private function createLineItem(PdkCartFee $fee, Delivery $delivery, SalesChannelContext $context, float $factor): ?LineItem
    {
        $shippingCosts = $delivery->getShippingCosts();
        $taxRules      = $shippingCosts->getTaxRules();

        // QuantityPriceCalculator reads the price as gross in a gross context.
        $price = (float) $fee->amount * $factor;

        if (CartPrice::TAX_STATE_GROSS === $context->getTaxState()) {
            $price *= 1 + ShippingTaxRate::of($taxRules);
        }

        if (self::PICKUP_FEE_ID === $fee->id) {
            $price = max(-$shippingCosts->getTotalPrice(), $price);
        }

        if (0.0 === round($price, 2)) {
            return null;
        }

        $definition = new QuantityPriceDefinition($price, $taxRules, 1);

        $lineItem = new LineItem('myparcel-fee-' . $fee->id, self::LINE_ITEM_TYPE, null, 1);
        $lineItem->setLabel(Language::translate($fee->translation));
        $lineItem->setGood(false);
        $lineItem->setRemovable(false);
        $lineItem->setStackable(false);
        $lineItem->setShippingCostAware(false);
        $lineItem->setPayloadValue('feeId', $fee->id);
        $lineItem->setPriceDefinition($definition);
        $lineItem->setPrice($this->priceCalculator->calculate($definition, $context));

        return $lineItem;
    }
}
