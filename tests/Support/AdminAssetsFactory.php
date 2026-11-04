<?php

declare(strict_types=1);

namespace MyParcel\Shopware\Tests\Support;

use MyParcel\Shopware\Admin\AdminAssets;
use Symfony\Component\Asset\Packages;
use Symfony\Component\Asset\UrlPackage;
use Symfony\Component\Asset\VersionStrategy\StaticVersionStrategy;

/**
 * AdminAssets with the bundle package of the plugin at a fixed shop URL and version.
 */
final class AdminAssetsFactory
{
    public static function create(): AdminAssets
    {
        return new AdminAssets(new Packages(null, [
            '@MyParcelShopware' => new UrlPackage('https://shop.test/bundles/myparcelshopware', new StaticVersionStrategy('1')),
        ]));
    }
}
