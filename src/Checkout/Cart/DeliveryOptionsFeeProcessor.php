<?php

declare(strict_types=1);

namespace MyParcel\Shopware\Checkout\Cart;

use MyParcel\Shopware\Checkout\CartDeliveryOptions;
use MyParcel\Shopware\Checkout\DeliveryOptionsFee;
use MyParcel\Shopware\Checkout\DeliveryOptionsGatewayInterface;
use MyParcel\Shopware\Pdk\Currency\EuroCurrency;
use MyParcel\Shopware\Pdk\Tax\ShippingTaxRate;
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
 * It runs after Shopware's DeliveryProcessor (-5000), so the shipping costs are
 * known, and before the shipping discounts of promotions (-5100). A fee is net
 * and in euro; the line gets the tax rules of the shipping costs. The pickup
 * discount never makes the shipping costs negative.
 *
 * Shopware calculates a cart from the start on every change, so every run adds
 * the lines again. When the admin recalculates an order, Shopware keeps the
 * shipping costs, and this processor keeps the fee lines of the order as they are.
 */
final class DeliveryOptionsFeeProcessor implements CartProcessorInterface
{
    public const LINE_ITEM_TYPE = 'myparcel_fee';

    public const PRIORITY = -5050;

    public function __construct(
        private readonly DeliveryOptionsGatewayInterface $gateway,
        private readonly QuantityPriceCalculator $priceCalculator,
        private readonly EuroCurrency $euroCurrency,
        private readonly LoggerInterface $logger
    ) {
    }

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
            $fees = $this->gateway->fees($toCalculate, $context, $deliveryOptions);
        } catch (Throwable $exception) {
            $this->logger->error('MyParcel: could not calculate the delivery option fees', ['exception' => $exception]);

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
     * Null for a fee of 0: the cart shows no line for it.
     */
    private function createLineItem(DeliveryOptionsFee $fee, Delivery $delivery, SalesChannelContext $context, float $factor): ?LineItem
    {
        $shippingCosts = $delivery->getShippingCosts();
        $taxRules      = $shippingCosts->getTaxRules();

        // QuantityPriceCalculator reads the price as gross in a gross context.
        $price = $fee->amount * $factor;

        if (CartPrice::TAX_STATE_GROSS === $context->getTaxState()) {
            $price *= 1 + ShippingTaxRate::of($taxRules);
        }

        if (DeliveryOptionsFee::PICKUP_ID === $fee->id) {
            $price = max(-$shippingCosts->getTotalPrice(), $price);
        }

        if (0.0 === round($price, 2)) {
            return null;
        }

        $definition = new QuantityPriceDefinition($price, $taxRules, 1);

        $lineItem = new LineItem('myparcel-fee-' . $fee->id, self::LINE_ITEM_TYPE, null, 1);
        $lineItem->setLabel($fee->label);
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
