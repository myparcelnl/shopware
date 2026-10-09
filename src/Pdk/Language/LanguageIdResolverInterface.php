<?php

declare(strict_types=1);

namespace MyParcel\Shopware\Pdk\Language;

interface LanguageIdResolverInterface
{
    /**
     * The id of the shop language with this locale, or null when the shop has none.
     */
    public function getLanguageId(string $locale): ?string;
}
