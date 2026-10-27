<?php

declare(strict_types=1);

namespace MyParcel\Shopware\Pdk;

use MyParcel\Shopware\Pdk\Language\LocaleResolverInterface;
use MyParcel\Shopware\Pdk\Routing\UrlResolverInterface;
use MyParcel\Shopware\Pdk\Storage\ConfigStorageInterface;
use MyParcelNL\Pdk\App\Order\Contract\OrderStatusServiceInterface;
use MyParcelNL\Pdk\Frontend\Contract\ViewServiceInterface;

/**
 * The Shopware-backed services the PDK contracts need, handed to the PDK
 * container in one go.
 */
final class ShopwareServices
{
    public function __construct(
        public readonly ConfigStorageInterface $configStorage,
        public readonly LocaleResolverInterface $localeResolver,
        public readonly UrlResolverInterface $urlResolver,
        public readonly ViewServiceInterface $viewService,
        public readonly OrderStatusServiceInterface $orderStatusService
    ) {
    }
}
