<?php

declare(strict_types=1);

namespace MyParcel\Shopware\Pdk\Order\Mapper;

use MyParcelNL\Pdk\Base\Contract\CurrencyServiceInterface;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\Price\Struct\CartPrice;

/**
 * Turns Shopware prices into the per-unit euro cents that PDK order lines and
 * shipment prices use.
 *
 * Shopware stores order amounts in the order currency. The currency factor of the
 * order is the rate from the system default currency to the order currency, and
 * the factor of the EUR currency is the rate from the default currency to euro.
 * amount / currency factor * euro factor is therefore the amount in euro, also
 * when the default currency is not EUR.
 *
 * The PDK keeps price, vat and price after vat per unit, and multiplies them by
 * the quantity (PdkOrder::updateTotals()). Shopware has the tax of the whole
 * position, so the vat per unit is rounded to cents. For a gross order the price
 * after vat is the exact Shopware unit price, so the PDK's totals after vat are
 * the Shopware totals, and the rounding goes into price (price after vat − vat).
 * For a net order the price is exact, and the total after vat can differ from
 * Shopware's by up to half a cent per unit.
 */
final class MoneyConverter
{
    public function __construct(private readonly CurrencyServiceInterface $currencyService)
    {
    }

    /**
     * Converts a Shopware calculated price into price, vat and price after vat per
     * unit, in euro cents. Use it for order lines and for the shipping costs. A
     * tax status other than CartPrice::TAX_STATE_NET or TAX_STATE_FREE counts as
     * gross.
     *
     * @return array{price: int, vat: int, priceAfterVat: int}
     */
    public function toUnitCents(CalculatedPrice $price, float $currencyFactor, float $euroFactor, string $taxStatus): array
    {
        $unit = $this->toCents($price->getUnitPrice(), $currencyFactor, $euroFactor);

        if (CartPrice::TAX_STATE_FREE === $taxStatus) {
            return ['price' => $unit, 'vat' => 0, 'priceAfterVat' => $unit];
        }

        $quantity = max(1, $price->getQuantity());
        $vat      = $this->toCents($price->getCalculatedTaxes()->getAmount() / $quantity, $currencyFactor, $euroFactor);

        if (CartPrice::TAX_STATE_NET === $taxStatus) {
            return ['price' => $unit, 'vat' => $vat, 'priceAfterVat' => $unit + $vat];
        }

        return ['price' => $unit - $vat, 'vat' => $vat, 'priceAfterVat' => $unit];
    }

    /**
     * Converts one amount in the order currency to euro cents. Use it for an amount
     * that is not a calculated price. A currency factor of 0 or less counts as 1.
     */
    public function toCents(float $amount, float $currencyFactor, float $euroFactor): int
    {
        $factor = $currencyFactor > 0 ? $currencyFactor : 1.0;

        return $this->currencyService->convertToCents($amount / $factor * $euroFactor);
    }
}
