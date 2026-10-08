<?php

declare(strict_types=1);

namespace MyParcel\Shopware\Pdk\Frontend;

use MyParcel\Shopware\Pdk\View\AdminView;

interface AdminViewRendererInterface
{
    /**
     * The HTML the admin app needs to show the view: the boot element, the
     * notifications, the modals and the view itself.
     */
    public function render(AdminView $view): string;
}
