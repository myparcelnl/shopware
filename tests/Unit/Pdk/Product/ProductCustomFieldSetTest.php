<?php

declare(strict_types=1);

use MyParcel\Shopware\Pdk\Product\ProductCustomFields;
use MyParcel\Shopware\Pdk\Product\ProductCustomFieldSet;
use MyParcel\Shopware\Tests\Support\FakeEntityRepository;
use MyParcel\Shopware\Tests\Support\RecordingConnection;
use MyParcel\Shopware\Tests\Support\TestPdk;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Uuid\Uuid;

beforeEach(fn () => TestPdk::boot());

it('upserts the field set and its fields with ids from their names', function () {
    $sets = FakeEntityRepository::with();

    (new ProductCustomFieldSet($sets, new RecordingConnection()))->sync(Context::createDefaultContext());

    $set = $sets->upserts[0];

    expect($sets->upserts)->toHaveCount(1)
        ->and($set['id'])->toBe(Uuid::fromStringToHex(ProductCustomFields::SET_NAME))
        ->and($set)->not->toHaveKey('relations')
        ->and($set['customFields'])->toHaveCount(count(ProductCustomFields::keys()))
        ->and($set['customFields'][0]['id'])->toBe(Uuid::fromStringToHex($set['customFields'][0]['name']))
        ->and($sets->deletes)->toBe([]);
});

it('deletes the field set and removes the values of every field from the products', function () {
    $sets       = FakeEntityRepository::with();
    $connection = new RecordingConnection();

    (new ProductCustomFieldSet($sets, $connection))->remove(Context::createDefaultContext());

    expect($sets->deletes)->toBe([['id' => Uuid::fromStringToHex(ProductCustomFields::SET_NAME)]])
        ->and($connection->statements)->toHaveCount(1)
        ->and($connection->statements[0]['sql'])->toStartWith('UPDATE `product_translation` SET `custom_fields` = JSON_REMOVE(`custom_fields`, ')
        ->and(substr_count($connection->statements[0]['sql'], "'$.myparcel_"))->toBe(count(ProductCustomFields::keys()))
        ->and($connection->statements[0]['sql'])->toContain("'$.myparcel_package_type'");
});
