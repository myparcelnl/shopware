<?php

declare(strict_types=1);

namespace MyParcel\Shopware\Pdk\Routing;

/**
 * The names of the plugin routes. The controllers declare the routes with these
 * names, and the PDK services build their URLs from them.
 */
final class RouteName
{
    public const ADMIN_PDK      = 'api.action.myparcel.pdk';
    public const ADMIN_VIEW     = 'api.action.myparcel.view';
    public const STOREFRONT_PDK = 'frontend.myparcel.pdk';
    public const WEBHOOK        = 'api.myparcel.webhook';
}
