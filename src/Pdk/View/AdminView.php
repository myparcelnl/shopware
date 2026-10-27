<?php

declare(strict_types=1);

namespace MyParcel\Shopware\Pdk\View;

/**
 * The admin screens that show a PDK view. The admin app asks for one by its
 * value.
 */
enum AdminView: string
{
    /**
     * The request attribute that holds the view while the PDK renders it.
     */
    public const REQUEST_ATTRIBUTE = 'myparcel_view';

    case PluginSettings = 'pluginSettings';
    case OrderList = 'orderList';
    case Order = 'order';
    case Product = 'product';
    case ChildProduct = 'childProduct';

    /**
     * True when the admin app may ask for this view by its value. The child
     * product is not requestable: the server picks that view itself.
     */
    public function isRequestable(): bool
    {
        return self::ChildProduct !== $this;
    }
}
