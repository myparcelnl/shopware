<?php

declare(strict_types=1);

namespace MyParcel\Shopware\Pdk\Frontend;

use MyParcel\Shopware\Pdk\PdkInitializer;
use MyParcel\Shopware\Pdk\View\AdminView;
use MyParcelNL\Pdk\Facade\Frontend;

final class AdminViewRenderer implements AdminViewRendererInterface
{
    public function __construct(private readonly PdkInitializer $pdkInitializer)
    {
    }

    /**
     * The view must already be on the request: ViewService reads it there to
     * decide what the PDK renders.
     */
    public function render(AdminView $view): string
    {
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
