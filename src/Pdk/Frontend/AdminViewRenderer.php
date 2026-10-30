<?php

declare(strict_types=1);

namespace MyParcel\Shopware\Pdk\Frontend;

use MyParcel\Shopware\Pdk\PdkInitializer;
use MyParcel\Shopware\Pdk\View\AdminScreen;
use MyParcelNL\Pdk\Facade\Frontend;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Renders an admin screen with the PDK for the view route of the admin API.
 *
 * @TODO INT-2151: cover this class with an integration test in a Shopware installation.
 */
final class AdminViewRenderer implements AdminViewRendererInterface
{
    public function __construct(
        private readonly PdkInitializer $pdkInitializer,
        private readonly RequestStack $requestStack
    ) {
    }

    /**
     * Render the screen with the bootstrap container, the notifications and the
     * modals. Puts the screen on the main request first: ViewService reads it
     * there to decide what the PDK renders.
     */
    public function render(AdminScreen $screen): string
    {
        $this->requestStack->getMainRequest()?->attributes->set(AdminScreen::REQUEST_ATTRIBUTE, $screen);

        $this->pdkInitializer->boot();

        return AdminViewMarkup::compose(
            $screen,
            Frontend::renderInitScript(),
            Frontend::renderNotifications(),
            Frontend::renderModals(),
            match ($screen) {
                AdminScreen::PluginSettings => Frontend::renderPluginSettings(),
            }
        );
    }
}
