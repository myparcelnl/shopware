<?php

declare(strict_types=1);

use MyParcel\Shopware\Pdk\Order\Mapper\AddressMapper;
use MyParcel\Shopware\Pdk\Order\Mapper\StreetSplitter;
use MyParcel\Shopware\Tests\Support\Entities;
use MyParcel\Shopware\Tests\Support\TestPdk;

beforeEach(fn () => TestPdk::boot());

function addressMapper(): AddressMapper
{
    return new AddressMapper(new StreetSplitter());
}

dataset('splits', [
    'NL number'            => [['street' => 'Teststraat 12'], 'Teststraat', '12', null, null, null],
    'NL letter suffix'     => [['street' => 'Teststraat 12A'], 'Teststraat', '12', 'A', null, null],
    'NL word suffix'       => [['street' => 'Teststraat 12-bis'], 'Teststraat', '12', 'bis', null, null],
    'BE box number'        => [['street' => 'Kerkstraat 16 bus 2', 'countryIso' => 'BE'], 'Kerkstraat', '16', null, '2', null],
    'number in line 1'     => [['street' => 'Teststraat', 'additionalAddressLine1' => '12', 'additionalAddressLine2' => 'Achterom'], 'Teststraat', '12', null, null, 'Achterom'],
    'suffix in line 1'     => [['street' => 'Teststraat', 'additionalAddressLine1' => '12A'], 'Teststraat', '12', 'A', null, null],
    'BE box in line 1'     => [['street' => 'Kerkstraat', 'additionalAddressLine1' => '16 bus 2', 'countryIso' => 'BE'], 'Kerkstraat', '16', null, '2', null],
    'lines stay info'      => [['street' => 'Teststraat 12', 'additionalAddressLine1' => '2e etage', 'additionalAddressLine2' => 'Achterom'], 'Teststraat', '12', null, null, '2e etage Achterom'],
]);

it('splits street, number, suffix and box number', function (
    array $values,
    string $street,
    string $number,
    ?string $suffix,
    ?string $box,
    ?string $info
) {
    $result = addressMapper()->map(Entities::address($values), null);

    expect($result->splitFailed)->toBeFalse()
        ->and($result->contactDetails)->toMatchArray([
            'street'               => $street,
            'number'               => $number,
            'numberSuffix'         => $suffix,
            'boxNumber'            => $box,
            'streetAdditionalInfo' => $info,
        ]);
})->with('splits');

it('sends the street unsplit and reports it when no number is found', function () {
    $result = addressMapper()->map(Entities::address(['street' => 'Teststraat']), null);

    expect($result->splitFailed)->toBeTrue()
        ->and($result->contactDetails['street'])->toBe('Teststraat')
        ->and($result->contactDetails['number'])->toBeNull();
});

dataset('line 1 without house number', [
    'NL apartment' => ['Teststraat', 'NL', 'Appartement 3'],
    'NL post box'  => ['Teststraat', 'NL', 'Postbus 12'],
    'BE apartment' => ['Kerkstraat', 'BE', 'Appartement 3'],
]);

it('does not take a number from line 1 that is not a house number', function (string $street, string $cc, string $line1) {
    $result = addressMapper()->map(Entities::address([
        'street'                 => $street,
        'additionalAddressLine1' => $line1,
        'countryIso'             => $cc,
    ]), null);

    expect($result->splitFailed)->toBeTrue()
        ->and($result->contactDetails)->toMatchArray([
            'street'               => $street,
            'number'               => null,
            'streetAdditionalInfo' => $line1,
        ]);
})->with('line 1 without house number');

it('does not split outside the countries with separate address fields', function () {
    $result = addressMapper()->map(Entities::address(['street' => 'Hauptstraße 5', 'countryIso' => 'DE', 'additionalAddressLine1' => 'Hinterhaus']), null);

    expect($result->splitFailed)->toBeFalse()
        ->and($result->contactDetails)->toMatchArray([
            'street'               => 'Hauptstraße 5',
            'number'               => null,
            'streetAdditionalInfo' => 'Hinterhaus',
            'cc'                   => 'DE',
        ]);
});

it('maps the contact fields', function () {
    $result = addressMapper()->map(Entities::address(['company' => 'Test BV', 'stateName' => 'Noord-Holland']), 'test@example.test');

    expect($result->contactDetails)->toMatchArray([
        'person'     => 'Jan Test',
        'company'    => 'Test BV',
        'email'      => 'test@example.test',
        'phone'      => '+31 6 00000000',
        'postalCode' => '1234 AB',
        'city'       => 'Teststad',
        'cc'         => 'NL',
        'region'     => 'Noord-Holland',
    ]);
});

it('gives empty contact details for a missing address', function () {
    $result = addressMapper()->map(null, 'test@example.test');

    expect($result->splitFailed)->toBeFalse()
        ->and($result->contactDetails)->toBe(['email' => 'test@example.test']);
});

dataset('house number in line 1', [
    'NL number in line 1'          => ['Teststraat', '12', 'NL', true],
    'NL digit in the street name'  => ['2e Hugo de Grootstraat', '12', 'NL', true],
    'BE box in line 1'             => ['Kerkstraat', '16 bus 2', 'BE', true],
    'lower case country'           => ['Teststraat', '12', 'nl', true],
    'number in the street'         => ['Teststraat 12', '2e etage', 'NL', false],
    'apartment in line 1'          => ['Teststraat', 'Appartement 3', 'NL', false],
    'empty line 1'                 => ['Teststraat', '', 'NL', false],
    'no separate address fields'   => ['Hauptstraße', '5', 'DE', false],
]);

it('tells if line 1 holds the house number', function (string $street, string $line1, string $cc, bool $expected) {
    expect(addressMapper()->houseNumberInLine1($street, $line1, $cc))->toBe($expected);
})->with('house number in line 1');

it('takes the house number from line 1 when the street name has a digit', function () {
    $result = addressMapper()->map(Entities::address(['street' => '2e Hugo de Grootstraat', 'additionalAddressLine1' => '12']), null);

    expect($result->splitFailed)->toBeFalse()
        ->and($result->contactDetails)->toMatchArray(['street' => '2e Hugo de Grootstraat', 'number' => '12']);
});
