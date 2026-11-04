<?php

declare(strict_types=1);

namespace MyParcel\Shopware\Pdk;

use MyParcel\Shopware\Checkout\CartLoaderInterface;
use MyParcel\Shopware\Checkout\SalesChannelContextHolder;
use MyParcel\Shopware\Pdk\Currency\EuroCurrency;
use MyParcel\Shopware\Pdk\Language\LocaleResolverInterface;
use MyParcel\Shopware\Pdk\Order\Writer\OrderDataWriter;
use MyParcel\Shopware\Pdk\Product\Writer\ProductSettingsWriter;
use MyParcel\Shopware\Pdk\Routing\UrlResolverInterface;
use MyParcel\Shopware\Pdk\Storage\ConfigStorageInterface;
use MyParcelNL\Pdk\App\Order\Contract\OrderStatusServiceInterface;
use MyParcelNL\Pdk\Frontend\Contract\ViewServiceInterface;
use Shopware\Core\Checkout\Order\OrderCollection;
use Shopware\Core\Checkout\Shipping\ShippingMethodCollection;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;

/**
 * The Shopware-backed services the PDK contracts need, handed to the PDK
 * container in one go.
 */
final class ShopwareServices
{
    /**
     * @param  EntityRepository<OrderCollection>   $orderRepository
     * @param  EntityRepository<ProductCollection> $productRepository
     * @param  EntityRepository<ShippingMethodCollection> $shippingMethodRepository
     */
    public function __construct(
        public readonly ConfigStorageInterface $configStorage,
        public readonly LocaleResolverInterface $localeResolver,
        public readonly UrlResolverInterface $urlResolver,
        public readonly EntityRepository $orderRepository,
        public readonly EntityRepository $productRepository,
        public readonly EuroCurrency $euroCurrency,
        public readonly OrderDataWriter $orderDataWriter,
        public readonly ProductSettingsWriter $productSettingsWriter,
        public readonly ViewServiceInterface $viewService,
        public readonly OrderStatusServiceInterface $orderStatusService,
        public readonly EntityRepository $shippingMethodRepository,
        public readonly SalesChannelContextHolder $contextHolder,
        public readonly CartLoaderInterface $cartLoader
    ) {
    }
}
