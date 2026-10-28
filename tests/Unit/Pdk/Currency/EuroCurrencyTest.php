<?php

declare(strict_types=1);

use MyParcel\Shopware\Pdk\Currency\EuroCurrency;
use MyParcel\Shopware\Tests\Support\Entities;
use MyParcel\Shopware\Tests\Support\FakeEntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;

it('returns the factor of the eur currency', function () {
    $currencies = FakeEntityRepository::with(Entities::currency('EUR', 0.9));

    expect((new EuroCurrency($currencies))->getFactor())->toBe(0.9)
        ->and($currencies->criteria[0]->getFilters())->toEqual([new EqualsFilter('isoCode', 'EUR')]);
});

it('throws a clear error when the shop has no eur currency', function () {
    expect(fn () => (new EuroCurrency(FakeEntityRepository::with()))->getFactor())
        ->toThrow(RuntimeException::class, 'the shop has no currency with ISO code EUR');
});
