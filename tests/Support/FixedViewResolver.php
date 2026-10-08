<?php

declare(strict_types=1);

namespace MyParcel\Shopware\Tests\Support;

use MyParcel\Shopware\Pdk\View\AdminView;
use MyParcel\Shopware\Pdk\View\ViewResolverInterface;

final class FixedViewResolver implements ViewResolverInterface
{
    public function __construct(private readonly ?AdminView $view)
    {
    }

    public function getView(): ?AdminView
    {
        return $this->view;
    }
}
