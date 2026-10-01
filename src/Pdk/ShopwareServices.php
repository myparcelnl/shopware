<?php

declare(strict_types=1);

namespace MyParcel\Shopware\Pdk;

use MyParcel\Shopware\Pdk\Language\LocaleResolverInterface;
use MyParcel\Shopware\Pdk\Storage\ConfigStorageInterface;

/**
 * The Shopware-backed services the PDK contracts need, handed to the PDK
 * container in one go.
 */
final class ShopwareServices
{
    public function __construct(
        public readonly ConfigStorageInterface $configStorage,
        public readonly LocaleResolverInterface $localeResolver
    ) {
    }
}
