<?php

declare(strict_types=1);

namespace MyParcel\Shopware\Pdk\View;

/**
 * The admin screens that show PDK components. Not the js-pdk AdminView, which
 * names the components themselves. Each screen is one ViewService page:
 * PluginSettings makes isPluginSettingsPage() true. The admin app asks for a
 * screen by its value.
 *
 * @TODO INT-1960: add the order list, the order detail and the product.
 */
enum AdminScreen: string
{
    /**
     * The request attribute that holds the screen while the PDK renders it.
     */
    public const REQUEST_ATTRIBUTE = 'myparcel_admin_screen';

    case PluginSettings = 'pluginSettings';
}
