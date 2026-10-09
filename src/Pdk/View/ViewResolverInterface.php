<?php

declare(strict_types=1);

namespace MyParcel\Shopware\Pdk\View;

interface ViewResolverInterface
{
    /**
     * The view of the current request, or null when the request renders none.
     */
    public function getView(): ?AdminView;
}
