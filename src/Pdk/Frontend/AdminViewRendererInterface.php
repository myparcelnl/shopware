<?php

declare(strict_types=1);

namespace MyParcel\Shopware\Pdk\Frontend;

use MyParcel\Shopware\Pdk\View\AdminScreen;

/**
 * A seam of our own so the controller can be tested without booting the PDK,
 * which needs the Shopware container.
 */
interface AdminViewRendererInterface
{
    /**
     * Render the HTML that the admin app needs to show the screen: the
     * bootstrap container, the notifications, the modals and the component of
     * the screen.
     */
    public function render(AdminScreen $screen): string;
}
