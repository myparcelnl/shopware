<?php

declare(strict_types=1);

namespace MyParcel\Shopware\Pdk\Frontend;

use MyParcel\Shopware\Pdk\View\AdminView;
use MyParcel\Shopware\Pdk\View\ViewResolverInterface;
use MyParcelNL\Pdk\Frontend\Service\AbstractViewService;

/**
 * Tells the PDK which screen it renders for. It maps the admin views. The
 * checkout is not mapped yet.
 *
 * @TODO INT-1961: recognise the checkout.
 */
final class ViewService extends AbstractViewService
{
    public function __construct(private readonly ViewResolverInterface $viewResolver)
    {
    }

    /**
     * Always false: the admin is never the checkout.
     */
    public function isCheckoutPage(): bool
    {
        return false;
    }

    /**
     * True when the view route renders the child product.
     */
    public function isChildProductPage(): bool
    {
        return AdminView::ChildProduct === $this->viewResolver->getView();
    }

    /**
     * True when the view route renders the order list.
     */
    public function isOrderListPage(): bool
    {
        return AdminView::OrderList === $this->viewResolver->getView();
    }

    /**
     * True when the view route renders the order detail.
     */
    public function isOrderPage(): bool
    {
        return AdminView::Order === $this->viewResolver->getView();
    }

    /**
     * True when the view route renders the plugin settings.
     */
    public function isPluginSettingsPage(): bool
    {
        return AdminView::PluginSettings === $this->viewResolver->getView();
    }

    /**
     * True when the view route renders the product.
     */
    public function isProductPage(): bool
    {
        return AdminView::Product === $this->viewResolver->getView();
    }
}
