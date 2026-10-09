<?php

declare(strict_types=1);

namespace MyParcel\Shopware\Tests\Support;

use MyParcel\Shopware\Pdk\Language\LanguageIdResolverInterface;

final class FixedLanguageIdResolver implements LanguageIdResolverInterface
{
    /**
     * @param  array<string, string> $languageIds locale code => language id
     */
    public function __construct(private readonly array $languageIds = [])
    {
    }

    public function getLanguageId(string $locale): ?string
    {
        return $this->languageIds[$locale] ?? null;
    }
}
