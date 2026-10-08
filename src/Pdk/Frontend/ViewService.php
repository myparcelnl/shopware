<?php

declare(strict_types=1);

namespace MyParcel\Shopware\Pdk\Frontend;

use MyParcel\Shopware\Pdk\View\AdminView;
use MyParcel\Shopware\Pdk\View\ViewResolverInterface;
use MyParcelNL\Pdk\Frontend\Service\AbstractViewService;

/**
 * Tells the PDK which screen it renders for. Only the plugin settings exist so
 * far: the order screens and the product follow in INT-1960, the checkout in
 * INT-1961.
 */
final class ViewService extends AbstractViewService
{
    public function __construct(private readonly ViewResolverInterface $viewResolver)
    {
    }

    public function isCheckoutPage(): bool
    {
        return false;
    }

    public function isChildProductPage(): bool
    {
        return false;
    }

    public function isOrderListPage(): bool
    {
        return false;
    }

    public function isOrderPage(): bool
    {
        return false;
    }

    public function isPluginSettingsPage(): bool
    {
        return AdminView::PluginSettings === $this->viewResolver->getView();
    }

    public function isProductPage(): bool
    {
        return false;
    }
}
