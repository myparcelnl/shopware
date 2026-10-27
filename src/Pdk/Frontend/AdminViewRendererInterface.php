<?php

declare(strict_types=1);

namespace MyParcel\Shopware\Pdk\Frontend;

use MyParcel\Shopware\Pdk\View\AdminViewRequest;

interface AdminViewRendererInterface
{
    /**
     * The HTML the admin app needs to show the view, with the order list
     * items and bulk actions for the order list.
     *
     * @throws AdminViewNotFoundException when the order of the order view does not exist
     */
    public function render(AdminViewRequest $request): AdminViewResult;
}
