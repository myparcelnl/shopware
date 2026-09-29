<?php

declare(strict_types=1);

namespace MyParcel\Shopware\Pdk\Language;

use MyParcelNL\Pdk\Base\FileSystemInterface;
use MyParcelNL\Pdk\Language\Repository\LanguageRepository;
use MyParcelNL\Pdk\Language\Service\AbstractLanguageService;

/**
 * Shows MyParcel texts in the language of the current Shopware request.
 *
 * The PDK maps the locale to a language it has translations for and falls
 * back to English for any other, see AbstractLanguageService::getIso2().
 */
final class LanguageService extends AbstractLanguageService
{
    private const FALLBACK_LOCALE = 'en';

    public function __construct(
        LanguageRepository $repository,
        FileSystemInterface $fileSystem,
        private readonly LocaleResolverInterface $localeResolver
    ) {
        parent::__construct($repository, $fileSystem);
    }

    public function getLanguage(): string
    {
        return $this->localeResolver->getLocale() ?? self::FALLBACK_LOCALE;
    }

    protected function getFilePath(?string $language = null): string
    {
        return sprintf('%s/config/pdk/translations/%s.json', dirname(__DIR__, 3), $this->getIso2($language));
    }
}
