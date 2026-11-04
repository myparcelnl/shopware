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

it('deletes the field set and removes the values of its stored fields from the products', function () {
    $sets       = FakeEntityRepository::with();
    // myparcel_dropped_setting stands for the field of a setting that the PDK dropped.
    $connection = new RecordingConnection(['myparcel_package_type', 'myparcel_dropped_setting']);
    $setId      = Uuid::fromStringToHex(ProductCustomFields::SET_NAME);

    (new ProductCustomFieldSet($sets, $connection))->remove(Context::createDefaultContext());

    expect($connection->queries)->toHaveCount(1)
        ->and($connection->queries[0]['params'])->toBe(['setId' => Uuid::fromHexToBytes($setId)])
        ->and($sets->deletes)->toBe([['id' => $setId]])
        ->and($connection->statements)->toHaveCount(1)
        ->and($connection->statements[0]['sql'])->toBe('UPDATE `product_translation` SET `custom_fields` = JSON_REMOVE(`custom_fields`, ?, ?) WHERE `custom_fields` IS NOT NULL')
        ->and($connection->statements[0]['params'])->toBe(['$.myparcel_package_type', '$.myparcel_dropped_setting']);
});

it('deletes the field set without an update when it has no fields', function () {
    $sets       = FakeEntityRepository::with();
    $connection = new RecordingConnection();

    (new ProductCustomFieldSet($sets, $connection))->remove(Context::createDefaultContext());

    expect($sets->deletes)->toBe([['id' => Uuid::fromStringToHex(ProductCustomFields::SET_NAME)]])
        ->and($connection->statements)->toBe([]);
});
