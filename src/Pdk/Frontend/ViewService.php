<?php

declare(strict_types=1);

namespace MyParcel\Shopware\Pdk\Frontend;

use MyParcel\Shopware\Pdk\View\AdminView;
use MyParcelNL\Pdk\Frontend\Service\AbstractViewService;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Tells the PDK which screen it renders for. The Shopware admin is a
 * single-page app, so no PHP page tells the PDK where it is: AdminViewRenderer
 * puts the view on the request instead. Only the plugin settings exist so far.
 *
 * @TODO INT-1960: recognise the order list, the order detail and the product page.
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
     * Tell if the view route renders the plugin settings.
     */
    public function isPluginSettingsPage(): bool
    {
        return AdminView::PluginSettings === $this->requestStack->getMainRequest()?->attributes->get(AdminView::REQUEST_ATTRIBUTE);
    }

    public function isProductPage(): bool
    {
        return false;
    }
}
