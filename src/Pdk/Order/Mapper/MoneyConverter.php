<?php

declare(strict_types=1);

namespace MyParcel\Shopware\Pdk\Order\Mapper;

use MyParcelNL\Pdk\Base\Contract\CurrencyServiceInterface;

/**
 * Turns Shopware prices into the per-unit cents in euro that PDK order lines and
 * shipment prices use.
 *
 * Shopware stores order amounts in the order currency. currencyFactor is the rate
 * from the system default currency to that currency, so dividing by it gives the
 * default currency. The plugin assumes that default currency is EUR; the storage
 * logs a warning when it is not.
 */
final class MoneyConverter
{
    public const TAX_STATUS_GROSS    = 'gross';
    public const TAX_STATUS_NET      = 'net';
    public const TAX_STATUS_TAX_FREE = 'tax-free';

    public function __construct(private readonly CurrencyServiceInterface $currencyService)
    {
    }

    /**
     * Converts a Shopware calculated price (unit price, quantity, taxes of the
     * whole position) into price, vat and price after vat per unit.
     *
     * @param  array{unitPrice: float|int, quantity: int, taxes: list<array{tax: float|int, taxRate: float|int}>} $price
     *
     * @return array{price: int, vat: int, priceAfterVat: int}
     */
    public function toUnitCents(array $price, float $currencyFactor, string $taxStatus): array
    {
        $unit = $this->toCents((float) $price['unitPrice'], $currencyFactor);

        if (self::TAX_STATUS_TAX_FREE === $taxStatus) {
            return ['price' => $unit, 'vat' => 0, 'priceAfterVat' => $unit];
        }

        $quantity = max(1, (int) $price['quantity']);
        $taxTotal = array_sum(array_map(static fn (array $tax): float => (float) $tax['tax'], $price['taxes']));
        $vat      = $this->toCents($taxTotal / $quantity, $currencyFactor);

        if (self::TAX_STATUS_NET === $taxStatus) {
            return ['price' => $unit, 'vat' => $vat, 'priceAfterVat' => $unit + $vat];
        }

        return ['price' => $unit - $vat, 'vat' => $vat, 'priceAfterVat' => $unit];
    }

    /**
     * Converts one amount in the order currency to euro cents.
     */
    public function toCents(float $amount, float $currencyFactor): int
    {
        $factor = $currencyFactor > 0 ? $currencyFactor : 1.0;

        return $this->currencyService->convertToCents($amount / $factor);
    }
}
