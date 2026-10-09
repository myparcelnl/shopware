<?php

declare(strict_types=1);

use MyParcel\Shopware\Admin\AdminAssets;
use MyParcel\Shopware\Tests\Support\PrefixAssetUrlResolver;

it('gives the url of the pdk admin script', function () {
    expect((new AdminAssets(new PrefixAssetUrlResolver()))->getScripts())
        ->toBe(['https://shop.test/bundles/myparcelshopware/pdk/admin.iife.js?1']);
});

it('gives the url of the pdk admin styles', function () {
    expect((new AdminAssets(new PrefixAssetUrlResolver()))->getStyles())
        ->toBe(['https://shop.test/bundles/myparcelshopware/pdk/admin.css?1']);
});
