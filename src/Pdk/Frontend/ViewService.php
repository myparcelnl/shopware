<?php

declare(strict_types=1);

namespace MyParcel\Shopware\Pdk\Frontend;

use MyParcel\Shopware\Pdk\View\AdminScreen;
use MyParcelNL\Pdk\Frontend\Service\AbstractViewService;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Tells the PDK which screen it renders for. The Shopware admin is a
 * single-page app, so no PHP page tells the PDK where it is: AdminViewRenderer
 * puts the screen on the request instead.
 *
 * @TODO INT-1961: recognise the checkout.
 */
final class ViewService extends AbstractViewService
{
    public function __construct(private readonly RequestStack $requestStack)
    {
    }

    public function isCheckoutPage(): bool
    {
        return false;
    }

    /**
     * Tell if the view route renders the settings of a variant.
     */
    public function isChildProductPage(): bool
    {
        return $this->isScreen(AdminScreen::ChildProduct);
    }

    /**
     * Tell if the view route renders the order list.
     */
    public function isOrderListPage(): bool
    {
        return $this->isScreen(AdminScreen::OrderList);
    }

    /**
     * Tell if the view route renders the order detail.
     */
    public function isOrderPage(): bool
    {
        return $this->isScreen(AdminScreen::Order);
    }

    /**
     * Tell if the view route renders the plugin settings.
     */
    public function isPluginSettingsPage(): bool
    {
        return $this->isScreen(AdminScreen::PluginSettings);
    }

    /**
     * Tell if the view route renders the settings of a product.
     */
    public function isProductPage(): bool
    {
        return $this->isScreen(AdminScreen::Product);
    }

    private function isScreen(AdminScreen $screen): bool
    {
        return $screen === $this->requestStack->getMainRequest()?->attributes->get(AdminScreen::REQUEST_ATTRIBUTE);
    }
}
