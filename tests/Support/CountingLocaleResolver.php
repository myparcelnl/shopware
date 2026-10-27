<?php

declare(strict_types=1);

namespace MyParcel\Shopware\Tests\Support;

use MyParcel\Shopware\Pdk\Language\LocaleResolverInterface;

final class CountingLocaleResolver implements LocaleResolverInterface
{
    public int $calls = 0;

    public function __construct(private readonly ?string $locale)
    {
    }

    public function getLocale(): ?string
    {
        $this->calls++;

        return $this->locale;
    }
}
