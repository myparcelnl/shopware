<?php

declare(strict_types=1);

namespace MyParcel\Shopware\Pdk\View;

/**
 * The admin screens that show PDK components. Not the js-pdk AdminView, which
 * names the components themselves. Each screen is one ViewService page:
 * PluginSettings makes isPluginSettingsPage() true. The admin app asks for a
 * screen by its value.
 */
enum AdminScreen: string
{
    /**
     * The request attribute that holds the screen while the PDK renders it.
     */
    public const REQUEST_ATTRIBUTE = 'myparcel_admin_screen';

    case PluginSettings = 'pluginSettings';
    case OrderList = 'orderList';
    case Order = 'order';
    case Product = 'product';
    case ChildProduct = 'childProduct';

    /**
     * Tell if the admin app may ask for this screen by its value. The child
     * product is not requestable: the server picks it for a variant itself.
     */
    public function isRequestable(): bool
    {
        return self::ChildProduct !== $this;
    }
}
