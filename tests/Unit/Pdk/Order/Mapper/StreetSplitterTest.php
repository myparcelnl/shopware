<?php

declare(strict_types=1);

use MyParcel\Shopware\Pdk\Order\Mapper\StreetSplitter;

dataset('dutch streets', [
    'number'             => ['Teststraat 12', 'Teststraat', '12', null],
    'letter suffix'      => ['Teststraat 12A', 'Teststraat', '12', 'A'],
    'suffix with a dash' => ['Teststraat 12-bis', 'Teststraat', '12', 'bis'],
    'suffix with space'  => ['Teststraat 12 A', 'Teststraat', '12', 'A'],
    'number in street'   => ['Plein 1940-1945 3', 'Plein 1940-1945', '3', null],
    'suffix word'        => ['Teststraat 12 noord', 'Teststraat', '12', 'N'],
    'line break'         => ["Teststraat\n12", 'Teststraat', '12', null],
]);

it('splits a dutch street', function (string $line, string $street, string $number, ?string $suffix) {
    expect((new StreetSplitter())->split($line, 'NL'))->toBe([
        'street'       => $street,
        'number'       => $number,
        'numberSuffix' => $suffix,
        'boxNumber'    => null,
    ]);
})->with('dutch streets');

dataset('belgian streets', [
    'number'           => ['Kerkstraat 16', 'Kerkstraat', '16', null, null],
    'bus'              => ['Kerkstraat 16 bus 2', 'Kerkstraat', '16', null, '2'],
    'bte'              => ['Kerkstraat 16 bte 2', 'Kerkstraat', '16', null, '2'],
    'boîte'            => ['Kerkstraat 16 boîte 3', 'Kerkstraat', '16', null, '3'],
    'slash'            => ['Kerkstraat 16/2', 'Kerkstraat', '16', null, '2'],
    'comma'            => ['Kerkstraat 16, 2', 'Kerkstraat', '16', null, '2'],
    'suffix and box'   => ['Kerkstraat 16A bus 2', 'Kerkstraat', '16', 'A', '2'],
    'words in street'  => ['Rue de la Loi 16', 'Rue de la Loi', '16', null, null],
]);

it('splits a belgian street', function (string $line, string $street, string $number, ?string $suffix, ?string $box) {
    expect((new StreetSplitter())->split($line, 'BE'))->toBe([
        'street'       => $street,
        'number'       => $number,
        'numberSuffix' => $suffix,
        'boxNumber'    => $box,
    ]);
})->with('belgian streets');

dataset('lines without a house number', [
    'NL street only'        => ['Teststraat', 'NL'],
    'NL number zero'        => ['Teststraat 0', 'NL'],
    'NL six digits'         => ['Teststraat 123456', 'NL'],
    'NL text after number'  => ['Teststraat 12 2e etage', 'NL'],
    'BE street only'        => ['Kerkstraat', 'BE'],
    'BE number zero'        => ['Kerkstraat 0', 'BE'],
    'other country'         => ['Hauptstraße 5', 'DE'],
]);

// PHPUnit turns a PHP warning into a failed test, so a pass also proves that no
// warning was raised.
it('returns null without a php warning when there is no house number', function (string $line, string $cc) {
    expect((new StreetSplitter())->split($line, $cc))->toBeNull();
})->with('lines without a house number');
