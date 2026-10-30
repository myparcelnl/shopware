<?php

declare(strict_types=1);

use MyParcel\Shopware\Tests\Support\AdminAssetsFactory;

it('gives the url of the pdk admin script', function () {
    expect(AdminAssetsFactory::create()->getScripts())
        ->toBe(['https://shop.test/bundles/myparcelshopware/pdk/admin.iife.js?1']);
});

it('gives the url of the pdk admin styles', function () {
    expect(AdminAssetsFactory::create()->getStyles())
        ->toBe(['https://shop.test/bundles/myparcelshopware/pdk/admin.css?1']);
});
