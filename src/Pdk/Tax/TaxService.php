<?php

declare(strict_types=1);

namespace MyParcel\Shopware\Pdk\Tax;

use MyParcel\Shopware\Checkout\CartLoaderInterface;
use MyParcel\Shopware\Checkout\SalesChannelContextHolder;
use MyParcelNL\Pdk\App\Tax\Contract\TaxServiceInterface;
use Shopware\Core\Checkout\Cart\Price\Struct\CartPrice;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;

/**
 * Gives the PDK the price of a delivery option as the consumer sees it.
 *
 * The PDK passes net prices. A sales channel context that shows gross prices
 * gets the tax of the shipping costs added: Shopware already chose those tax
 * rules for the shipping method (auto, highest or fixed), so the delivery
 * options follow the same rate. Net and tax-free contexts get the price as it is.
 */
final class TaxService implements TaxServiceInterface
{
    public function __construct(
        private readonly SalesChannelContextHolder $contextHolder,
        private readonly CartLoaderInterface $cartLoader
    ) {
    }

    public function getShippingDisplayPrice(float $basePrice): float
    {
        if (!$this->contextHolder->has()) {
            return $basePrice;
        }

        $context = $this->contextHolder->get();

        if (CartPrice::TAX_STATE_GROSS !== $context->getTaxState()) {
            return $basePrice;
        }

        $taxRules = $this->cartLoader->load($context)->getDeliveries()->first()?->getShippingCosts()->getTaxRules();

        return round($basePrice * (1 + ShippingTaxRate::of($taxRules ?? new TaxRuleCollection())), 2);
    }
}
