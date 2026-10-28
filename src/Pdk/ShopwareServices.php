<?php

declare(strict_types=1);

namespace MyParcel\Shopware\Pdk;

use MyParcel\Shopware\Pdk\Currency\EuroCurrency;
use MyParcel\Shopware\Pdk\Language\LocaleResolverInterface;
use MyParcel\Shopware\Pdk\Order\Contract\OrderStateTransitionerInterface;
use MyParcel\Shopware\Pdk\Order\Writer\OrderDataWriter;
use MyParcel\Shopware\Pdk\Product\Contract\ProductStorageInterface;
use MyParcel\Shopware\Pdk\Routing\UrlResolverInterface;
use MyParcel\Shopware\Pdk\Storage\ConfigStorageInterface;
use Shopware\Core\Checkout\Order\OrderCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;

/**
 * The Shopware-backed services the PDK contracts need, handed to the PDK
 * container in one go.
 */
final class ShopwareServices
{
    /**
     * @param  EntityRepository<OrderCollection> $orderRepository
     */
    public function __construct(
        public readonly ConfigStorageInterface $configStorage,
        public readonly LocaleResolverInterface $localeResolver,
        public readonly UrlResolverInterface $urlResolver,
        public readonly EntityRepository $orderRepository,
        public readonly ProductStorageInterface $productStorage,
        public readonly EuroCurrency $euroCurrency,
        public readonly OrderDataWriter $orderDataWriter,
        public readonly OrderStateTransitionerInterface $orderStateTransitioner
    ) {
    }
}
