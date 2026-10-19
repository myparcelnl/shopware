<?php

declare(strict_types=1);

use MyParcel\Shopware\Pdk\Product\ProductCustomFields;
use MyParcel\Shopware\Tests\Support\TestPdk;
use MyParcelNL\Pdk\Settings\Model\ProductSettings;
use MyParcelNL\Pdk\Types\Service\TriStateService;

beforeEach(fn () => TestPdk::boot());

it('lists every product setting, including the dynamic export options', function () {
    expect(ProductCustomFields::keys())
        ->toContain(ProductSettings::PACKAGE_TYPE, ProductSettings::CUSTOMS_CODE, ProductSettings::FIT_IN_MAILBOX, 'exportSignature')
        ->not->toContain('id');
});

it('names fields with the prefix and in snake case', function () {
    expect(ProductCustomFields::fieldName('fitInMailbox'))->toBe('myparcel_fit_in_mailbox')
        ->and(ProductCustomFields::fieldName('exportAgeCheck'))->toBe('myparcel_export_age_check');
});

it('reads settings from custom fields, missing values inherit', function () {
    $settings = ProductCustomFields::toSettings([
        'myparcel_package_type'      => 'mailbox',
        'myparcel_fit_in_mailbox'    => '3',
        'myparcel_customs_code'      => '  ',
        'myparcel_export_signature'  => 1,
        'myparcel_export_age_check'  => 'not-a-tri-state',
        'other_plugin_field'         => 'ignored',
    ]);

    expect($settings->packageType)->toBe('mailbox')
        ->and($settings->fitInMailbox)->toBe(3)
        ->and($settings->customsCode)->toBe(TriStateService::INHERIT)
        ->and($settings->exportSignature)->toBe(TriStateService::ENABLED)
        ->and($settings->exportAgeCheck)->toBe(TriStateService::INHERIT)
        ->and($settings->countryOfOrigin)->toBe(TriStateService::INHERIT);
});

it('writes settings as custom fields, inherit as null', function () {
    $fields = ProductCustomFields::toCustomFields(new ProductSettings([
        ProductSettings::PACKAGE_TYPE   => 'mailbox',
        ProductSettings::FIT_IN_MAILBOX => 2,
        ProductSettings::CUSTOMS_CODE   => '61091000',
        'exportSignature'               => TriStateService::DISABLED,
    ]));

    expect($fields)->toMatchArray([
        'myparcel_package_type'     => 'mailbox',
        'myparcel_fit_in_mailbox'   => 2,
        'myparcel_customs_code'     => '61091000',
        'myparcel_export_signature' => 0,
        'myparcel_export_age_check' => null,
        'myparcel_country_of_origin' => null,
    ])->and(array_keys($fields))->toHaveCount(count(ProductCustomFields::keys()));
});

it('round-trips settings through custom fields', function () {
    $original = new ProductSettings([ProductSettings::PACKAGE_TYPE => 'package_small', ProductSettings::DROP_OFF_DELAY => 2]);

    $copy = ProductCustomFields::toSettings(ProductCustomFields::toCustomFields($original));

    expect($copy->toStorableArray())->toBe($original->toStorableArray());
});

it('keeps only its own keys', function () {
    expect(ProductCustomFields::onlyOwnKeys(['myparcel_package_type' => 'mailbox', 'other' => 1]))
        ->toBe(['myparcel_package_type' => 'mailbox'])
        ->and(ProductCustomFields::onlyOwnKeys(null))->toBe([]);
});

it('defines a field per setting with a fitting component', function () {
    $definitions = array_column(ProductCustomFields::fieldDefinitions(), null, 'name');

    expect($definitions)->toHaveCount(count(ProductCustomFields::keys()))
        ->and($definitions['myparcel_customs_code']['type'])->toBe('text')
        ->and($definitions['myparcel_fit_in_mailbox']['type'])->toBe('int')
        ->and($definitions['myparcel_export_signature']['config']['componentName'])->toBe('sw-single-select')
        ->and(array_column($definitions['myparcel_export_signature']['config']['options'], 'value'))->toBe([-1, 0, 1])
        ->and(array_column($definitions['myparcel_package_type']['config']['options'], 'value'))->toContain('package', 'mailbox');
});
