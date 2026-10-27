<?php

declare(strict_types=1);

use MyParcel\Shopware\Admin\AdminAssets;
use Symfony\Component\Asset\Packages;
use Symfony\Component\Asset\UrlPackage;
use Symfony\Component\Asset\VersionStrategy\StaticVersionStrategy;

function adminAssets(): AdminAssets
{
    $bundlePackage = new UrlPackage('https://shop.test/bundles/myparcelshopware', new StaticVersionStrategy('1'));

    return new AdminAssets(new Packages(null, ['@MyParcelShopware' => $bundlePackage]));
}

it('gives the url of the pdk admin script', function () {
    expect(adminAssets()->getScripts())
        ->toBe(['https://shop.test/bundles/myparcelshopware/pdk/admin.iife.js?1']);
});

it('gives the url of the pdk admin styles', function () {
    expect(adminAssets()->getStyles())
        ->toBe(['https://shop.test/bundles/myparcelshopware/pdk/admin.css?1']);
});
