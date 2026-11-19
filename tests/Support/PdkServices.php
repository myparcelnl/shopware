<?php

declare(strict_types=1);

namespace MyParcel\Shopware\Tests\Support;

use MyParcel\Shopware\Checkout\SalesChannelContextHolder;
use MyParcel\Shopware\Pdk\Cart\Mapper\PdkCartMapper;
use MyParcel\Shopware\Pdk\Cart\Repository\PdkCartRepository;
use MyParcel\Shopware\Pdk\Currency\EuroCurrency;
use MyParcel\Shopware\Pdk\Frontend\ViewService;
use MyParcel\Shopware\Pdk\Order\Mapper\MoneyConverter;
use MyParcel\Shopware\Pdk\Order\Writer\OrderDataWriter;
use MyParcel\Shopware\Pdk\PdkInitializer;
use MyParcel\Shopware\Pdk\Product\Mapper\PdkProductMapper;
use MyParcel\Shopware\Pdk\Product\Repository\PdkProductRepository;
use MyParcel\Shopware\Pdk\Product\Writer\ProductSettingsWriter;
use MyParcelNL\Pdk\App\Cart\Contract\PdkCartRepositoryInterface;
use MyParcelNL\Pdk\Base\Service\CurrencyService;
use MyParcelNL\Pdk\Base\Service\WeightService;
use MyParcelNL\Pdk\Storage\MemoryCacheStorage;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * The plugin's own PDK services, built with fakes, for the tests that run the
 * production classes in the mock PDK.
 */
final class PdkServices
{
    /**
     * An initializer with fakes. After TestPdk::boot(), its boot() gives the
     * mock PDK.
     */
    public static function initializer(
        ?RecordingLogger $logger = null,
        ?SalesChannelContextHolder $contextHolder = null,
        ?FixedCartLoader $cartLoader = null
    ): PdkInitializer {
        return new PdkInitializer(
            $logger ?? new RecordingLogger(),
            new InMemoryConfigStorage(),
            new FixedLocaleResolver(null),
            new FixedUrlResolver([]),
            FakeEntityRepository::with(),
            FakeEntityRepository::with(),
            new EuroCurrency(FakeEntityRepository::with()),
            new OrderDataWriter(FakeEntityRepository::with(), FakeEntityRepository::with(), new RecordingConnection()),
            new ProductSettingsWriter(FakeEntityRepository::with()),
            new ViewService(new RequestStack()),
            new FixedOrderStatusService(),
            FakeEntityRepository::with(),
            $contextHolder ?? new SalesChannelContextHolder(),
            $cartLoader ?? new FixedCartLoader(),
            '0.0.0-test',
            'https://shop.test',
            true
        );
    }

    /**
     * Our cart repository, with the products of the Entities fixtures.
     */
    public static function cartRepository(
        SalesChannelContextHolder $contextHolder,
        ?FixedCartLoader $cartLoader = null,
        ?FakeEntityRepository $products = null
    ): PdkCartRepository {
        $euroCurrency = new EuroCurrency(FakeEntityRepository::with(Entities::currency('EUR', 1.0)));

        return new PdkCartRepository(
            new MemoryCacheStorage(),
            $contextHolder,
            $cartLoader ?? new FixedCartLoader(Checkout::cart()),
            new PdkProductRepository(
                new MemoryCacheStorage(),
                $products ?? FakeEntityRepository::with(Entities::product()),
                $euroCurrency,
                new PdkProductMapper(new CurrencyService(), new WeightService()),
                new ProductSettingsWriter(FakeEntityRepository::with())
            ),
            $euroCurrency,
            new PdkCartMapper(new MoneyConverter(new CurrencyService()))
        );
    }

    /**
     * Boots the mock PDK with our cart repository on the given holder, as the
     * production container binds it.
     */
    public static function bootWithCartRepository(SalesChannelContextHolder $contextHolder): void
    {
        TestPdk::boot([PdkCartRepositoryInterface::class => self::cartRepository($contextHolder)]);
    }
}
