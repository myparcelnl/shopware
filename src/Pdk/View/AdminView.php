<?php

declare(strict_types=1);

namespace MyParcel\Shopware\Pdk\View;

/**
 * The admin screens that show a PDK view. The admin app asks for one by its
 * value; INT-1960 adds the order list, the order detail and the product.
 */
enum AdminView: string
{
    /**
     * The request attribute that holds the view while the PDK renders it.
     */
    public const REQUEST_ATTRIBUTE = 'myparcel_view';

    case PluginSettings = 'pluginSettings';
}
