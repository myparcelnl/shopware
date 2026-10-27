<?php

declare(strict_types=1);

namespace MyParcel\Shopware\Pdk\Frontend;

use MyParcel\Shopware\Pdk\PdkInitializer;
use MyParcel\Shopware\Pdk\View\AdminView;
use MyParcelNL\Pdk\Facade\Frontend;
use Symfony\Component\HttpFoundation\RequestStack;

final class AdminViewRenderer implements AdminViewRendererInterface
{
    public function __construct(
        private readonly PdkInitializer $pdkInitializer,
        private readonly RequestStack $requestStack
    ) {
    }

    /**
     * Render the view with the bootstrap container, the notifications and the
     * modals. Puts the view on the main request first: ViewService reads it
     * there to decide what the PDK renders.
     */
    public function render(AdminView $view): string
    {
        $this->requestStack->getMainRequest()?->attributes->set(AdminView::REQUEST_ATTRIBUTE, $view);

        $this->pdkInitializer->boot();

        return AdminViewMarkup::compose(
            $view,
            Frontend::renderInitScript(),
            Frontend::renderNotifications(),
            Frontend::renderModals(),
            match ($view) {
                AdminView::PluginSettings => Frontend::renderPluginSettings(),
            }
        );
    }
}
