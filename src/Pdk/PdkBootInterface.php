<?php

declare(strict_types=1);

namespace MyParcel\Shopware\Pdk;

use MyParcelNL\Pdk\Base\Pdk;

interface PdkBootInterface
{
    /**
     * Boots the PDK, so its facades work. Safe to call repeatedly.
     */
    public function boot(): Pdk;
}
