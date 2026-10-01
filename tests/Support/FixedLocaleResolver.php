<?php

declare(strict_types=1);

namespace MyParcel\Shopware\Tests\Support;

use MyParcel\Shopware\Pdk\Language\LocaleResolverInterface;

final class FixedLocaleResolver implements LocaleResolverInterface
{
    public function __construct(private readonly ?string $locale)
    {
    }

    public function getLocale(): ?string
    {
        return $this->locale;
    }
}
