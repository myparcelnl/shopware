<?php

declare(strict_types=1);

use MyParcel\Shopware\Pdk\Product\Writer\ProductSettingsWriter;
use MyParcel\Shopware\Tests\Support\Entities;
use MyParcel\Shopware\Tests\Support\FakeEntityRepository;

it('updates only the myparcel custom fields of the product', function () {
    $products = FakeEntityRepository::with();

    (new ProductSettingsWriter($products))->save(Entities::PRODUCT_ID, [
        'myparcel_package_type' => 'mailbox',
        'myparcel_customs_code' => null,
        'other_plugin_field'    => 'ignored',
    ]);

    expect($products->updates)->toBe([[
        'id'           => Entities::PRODUCT_ID,
        'customFields' => ['myparcel_package_type' => 'mailbox', 'myparcel_customs_code' => null],
    ]]);
});
