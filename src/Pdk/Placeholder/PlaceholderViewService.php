<?php

declare(strict_types=1);

namespace MyParcel\Shopware\Pdk\Placeholder;

use MyParcelNL\Pdk\Frontend\Contract\ViewServiceInterface;

/**
 * Temporary. It exists only so that the PDK container compiles in production
 * mode. The adapter-layer tickets of epic INT-1748 replace it with the real
 * implementation.
 */
final class PlaceholderViewService implements ViewServiceInterface
{
    public function hasModals(): bool
    {
        throw NotImplementedException::forContract(ViewServiceInterface::class);
    }

    public function hasNotifications(): bool
    {
        throw NotImplementedException::forContract(ViewServiceInterface::class);
    }

    public function isAnyPdkPage(): bool
    {
        throw NotImplementedException::forContract(ViewServiceInterface::class);
    }

    public function isCheckoutPage(): bool
    {
        throw NotImplementedException::forContract(ViewServiceInterface::class);
    }

    public function isChildProductPage(): bool
    {
        throw NotImplementedException::forContract(ViewServiceInterface::class);
    }

    public function isOrderListPage(): bool
    {
        throw NotImplementedException::forContract(ViewServiceInterface::class);
    }

    public function isOrderPage(): bool
    {
        throw NotImplementedException::forContract(ViewServiceInterface::class);
    }

    public function isPluginSettingsPage(): bool
    {
        throw NotImplementedException::forContract(ViewServiceInterface::class);
    }

    public function isProductPage(): bool
    {
        throw NotImplementedException::forContract(ViewServiceInterface::class);
    }
}
