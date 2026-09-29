<?php

declare(strict_types=1);

use MyParcel\Shopware\Pdk\Language\LanguageService;
use MyParcel\Shopware\Tests\Support\FixedLocaleResolver;
use MyParcelNL\Pdk\Api\Contract\ApiServiceInterface;
use MyParcelNL\Pdk\Base\FileSystem;
use MyParcelNL\Pdk\Language\Repository\LanguageRepository;
use MyParcelNL\Pdk\Language\Service\AbstractLanguageService;
use MyParcelNL\Pdk\Storage\MemoryCacheStorage;
use PHPUnit\Framework\TestCase;

function languageService(TestCase $test, ?string $locale): LanguageService
{
    return new LanguageService(
        new LanguageRepository(
            new MemoryCacheStorage(),
            $test->getMockBuilder(ApiServiceInterface::class)->getMock()
        ),
        new FileSystem(),
        new FixedLocaleResolver($locale)
    );
}

function translationFile(LanguageService $service, string $language): string
{
    return (new ReflectionMethod($service, 'getFilePath'))->invoke($service, $language);
}

it('is a pdk language service', function () {
    expect(languageService($this, 'nl-NL'))->toBeInstanceOf(AbstractLanguageService::class);
});

it('uses the locale of the current shopware request', function () {
    expect(languageService($this, 'de-DE')->getLanguage())->toBe('de-DE');
});

it('falls back to english outside a request', function () {
    expect(languageService($this, null)->getLanguage())->toBe('en');
});

it('maps a language to its translation file', function (string $language, string $file) {
    expect(translationFile(languageService($this, null), $language))
        ->toBe(dirname(__DIR__, 4) . "/config/pdk/translations/{$file}");
})->with([
    ['nl-NL', 'nl.json'],
    ['fr-BE', 'fr.json'],
    ['de', 'de.json'],
]);

it('ships a translation file for every required language', function (string $iso2) {
    $file = translationFile(languageService($this, null), $iso2);

    expect($file)->toBeFile()
        ->and(json_decode((string) file_get_contents($file), true))->toBeArray()->not->toBeEmpty();
})->with(['nl', 'en', 'de', 'fr']);
