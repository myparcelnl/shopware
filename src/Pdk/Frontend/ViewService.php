<?php

declare(strict_types=1);

namespace MyParcel\Shopware\Pdk\Frontend;

use MyParcel\Shopware\Pdk\View\AdminView;
use MyParcel\Shopware\Pdk\View\ViewResolverInterface;
use MyParcelNL\Pdk\Frontend\Service\AbstractViewService;

/**
 * Tells the PDK which screen it renders for. Only the plugin settings exist so
 * far.
 *
 * @TODO INT-1960: recognise the order list, the order detail and the product page.
 * @TODO INT-1961: recognise the checkout.
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

    /**
     * True when the view route renders the plugin settings.
     */
    public function isPluginSettingsPage(): bool
    {
        return AdminView::PluginSettings === $this->viewResolver->getView();
    }

    public function isProductPage(): bool
    {
        return false;
    }
}
