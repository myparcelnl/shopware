<?php

declare(strict_types=1);

namespace MyParcel\Shopware\Pdk\Tax;

use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;

/**
 * The tax rate of the shipping costs. Shopware already chose those tax rules for
 * the shipping method (auto, highest or fixed), so the delivery option prices
 * and fees follow the same rate.
 */
final class ShippingTaxRate
{
    /**
     * The weighted rate as a fraction: rules of 21 % for 60 % and 9 % for 40 %
     * give 0.162.
     */
    public static function of(TaxRuleCollection $taxRules): float
    {
        $rate = 0.0;

        foreach ($taxRules as $rule) {
            $rate += $rule->getTaxRate() / 100 * $rule->getPercentage() / 100;
        }

        return $rate;
    }
}
