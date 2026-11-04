<?php

declare(strict_types=1);

namespace MyParcel\Shopware\Pdk\Currency;

use RuntimeException;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\System\Currency\CurrencyCollection;
use Shopware\Core\System\Currency\CurrencyEntity;

/**
 * The EUR currency of the shop. MyParcel takes amounts in euro, and Shopware
 * stores them in the system default currency or in the order currency, which
 * need not be euro.
 */
final class EuroCurrency
{
    /**
     * @param  EntityRepository<CurrencyCollection> $currencyRepository
     */
    public function __construct(private readonly EntityRepository $currencyRepository)
    {
    }

    /**
     * Returns the factor of the EUR currency: the rate from the system default
     * currency to euro (1.0 when the default currency is EUR). Use it to convert
     * Shopware amounts to euro. Reads the currency table on every call, so a
     * changed rate counts at once.
     *
     * @throws RuntimeException when the shop has no currency with ISO code EUR
     */
    public function getFactor(): float
    {
        $criteria = (new Criteria())->addFilter(new EqualsFilter('isoCode', 'EUR'))->setLimit(1);

        /** @var null|CurrencyEntity $currency */
        $currency = $this->currencyRepository->search($criteria, Context::createDefaultContext())->first();

        if (null === $currency) {
            throw new RuntimeException('MyParcel needs the currency EUR to convert amounts, and the shop has no currency with ISO code EUR. Add it under Settings > Currencies.');
        }

        return $currency->getFactor();
    }
}
