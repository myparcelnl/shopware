<?php

declare(strict_types=1);

use MyParcel\Shopware\Pdk\Language\LocaleLanguageChain;
use MyParcel\Shopware\Tests\Support\FixedLanguageIdResolver;

const LANGUAGE_EN = 'en000000000000000000000000000000';
const LANGUAGE_DE = 'de000000000000000000000000000000';

it('puts the language of the locale first and keeps the request chain as fallback', function () {
    $chain = LocaleLanguageChain::build([LANGUAGE_EN], 'de-DE', new FixedLanguageIdResolver(['de-DE' => LANGUAGE_DE]));

    expect($chain)->toBe([LANGUAGE_DE, LANGUAGE_EN]);
});

it('keeps the request chain when the shop has no language for the locale', function () {
    $chain = LocaleLanguageChain::build([LANGUAGE_EN], 'nl-NL', new FixedLanguageIdResolver(['de-DE' => LANGUAGE_DE]));

    expect($chain)->toBe([LANGUAGE_EN]);
});

it('keeps the request chain without a locale', function () {
    expect(LocaleLanguageChain::build([LANGUAGE_EN], null, new FixedLanguageIdResolver()))->toBe([LANGUAGE_EN]);
});

it('does not repeat a language that is already in the chain', function () {
    $chain = LocaleLanguageChain::build([LANGUAGE_DE, LANGUAGE_EN], 'de-DE', new FixedLanguageIdResolver(['de-DE' => LANGUAGE_DE]));

    expect($chain)->toBe([LANGUAGE_DE, LANGUAGE_EN]);
});
